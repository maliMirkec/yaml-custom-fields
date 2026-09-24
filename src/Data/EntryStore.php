<?php

/**
 * Entry Store
 * Where the items of a collection are kept
 */

namespace YamlCF\Data;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Every item of a collection used to live in one option per collection, read and
 * written whole. A collection of five thousand people meant unserialising all
 * five thousand to show one of them, to search for one of them, or to save one
 * of them — on every keystroke in the picker.
 *
 * Each item now has an option of its own, next to a small index of id => label
 * for the collection. Reading one item reads one small option. Searching and
 * listing read the index, which holds labels and nothing else, and then fetch
 * only the items actually being shown.
 *
 * Items saved by an earlier version are still read from the old option until the
 * one-off move in YamlCF\Core\Upgrader has run, so nothing depends on the order
 * the two happen in.
 */
class EntryStore {
  /** Prefix for the option holding one item */
  const ENTRY_PREFIX = 'yaml_cf_do_entry_';

  /** Prefix for the option holding a collection's id => label index */
  const INDEX_PREFIX = 'yaml_cf_do_index_';

  /** Prefix of the option each collection used to keep every item in */
  const LEGACY_PREFIX = 'yaml_cf_data_object_entries_';

  /**
   * The id => label index for a collection
   *
   * @param string $type_slug Collection slug
   * @return array<string, string> Labels keyed by entry id, in display order
   */
  public function index($type_slug) {
    $index = get_option(self::INDEX_PREFIX . $type_slug, null);

    if (is_array($index)) {
      return $index;
    }

    // Not moved yet: build the index from the old option without writing anything
    $index = [];
    foreach ($this->legacyEntries($type_slug) as $entry_id => $entry) {
      $index[(string) $entry_id] = $this->labelFor($entry, (string) $entry_id);
    }

    return $index;
  }

  /**
   * How many items a collection holds
   *
   * Reads the index only, never the items.
   *
   * @param string $type_slug Collection slug
   * @return int
   */
  public function count($type_slug) {
    return count($this->index($type_slug));
  }

  /**
   * One item
   *
   * @param string $type_slug Collection slug
   * @param string $entry_id Entry id
   * @return array|null The item, or null when there is none
   */
  public function get($type_slug, $entry_id) {
    $entry = get_option(self::ENTRY_PREFIX . $this->key($type_slug, $entry_id), null);

    if (is_array($entry)) {
      return $entry;
    }

    $legacy = $this->legacyEntries($type_slug);

    return isset($legacy[$entry_id]) && is_array($legacy[$entry_id]) ? $legacy[$entry_id] : null;
  }

  /**
   * Several items by id, in the order asked for
   *
   * @param string $type_slug Collection slug
   * @param string[] $entry_ids Entry ids
   * @return array<string, array> Items keyed by entry id
   */
  public function getMany($type_slug, array $entry_ids) {
    $entries = [];

    foreach ($entry_ids as $entry_id) {
      $entry = $this->get($type_slug, (string) $entry_id);
      if ($entry !== null) {
        $entries[(string) $entry_id] = $entry;
      }
    }

    return $entries;
  }

  /**
   * Every item of a collection
   *
   * Reads all of them, so it is for exporting and for the template function that
   * promises every item. Anything showing a page of items or searching should
   * use index(), search() or getMany() instead.
   *
   * @param string $type_slug Collection slug
   * @return array<string, array> Items keyed by entry id
   */
  public function all($type_slug) {
    return $this->getMany($type_slug, array_keys($this->index($type_slug)));
  }

  /**
   * Entry ids whose label matches, without reading the items
   *
   * @param string $type_slug Collection slug
   * @param string $search Text to look for; empty matches everything
   * @param int $limit Most ids to return
   * @return array{ids: string[], total: int} Matching ids, and how many matched in all
   */
  public function search($type_slug, $search, $limit) {
    $matched = [];

    foreach ($this->index($type_slug) as $entry_id => $label) {
      if ($search === '' || stripos((string) $label, $search) !== false) {
        $matched[] = (string) $entry_id;
      }
    }

    return [
      'ids' => $limit > 0 ? array_slice($matched, 0, $limit) : $matched,
      'total' => count($matched),
    ];
  }

  /**
   * Save one item
   *
   * @param string $type_slug Collection slug
   * @param string $entry_id Entry id
   * @param array $data The item
   * @param string $label_field Field whose value labels the item
   * @return bool
   */
  public function save($type_slug, $entry_id, $data, $label_field = '') {
    $this->ensureMoved($type_slug);

    update_option(self::ENTRY_PREFIX . $this->key($type_slug, $entry_id), $data, false);

    $index = $this->index($type_slug);
    $index[(string) $entry_id] = $this->labelFor($data, (string) $entry_id, $label_field);

    return update_option(self::INDEX_PREFIX . $type_slug, $index, false);
  }

  /**
   * Delete one item
   *
   * @param string $type_slug Collection slug
   * @param string $entry_id Entry id
   * @return bool
   */
  public function delete($type_slug, $entry_id) {
    $this->ensureMoved($type_slug);

    delete_option(self::ENTRY_PREFIX . $this->key($type_slug, $entry_id));

    $index = $this->index($type_slug);
    unset($index[(string) $entry_id]);

    return update_option(self::INDEX_PREFIX . $type_slug, $index, false);
  }

  /**
   * Delete every item of a collection
   *
   * The index is what makes this possible without asking the database for option
   * names that look like ours, which this plugin does not do outside uninstall.
   *
   * @param string $type_slug Collection slug
   * @return bool
   */
  public function deleteAll($type_slug) {
    foreach (array_keys($this->index($type_slug)) as $entry_id) {
      delete_option(self::ENTRY_PREFIX . $this->key($type_slug, (string) $entry_id));
    }

    delete_option(self::INDEX_PREFIX . $type_slug);
    delete_option(self::LEGACY_PREFIX . $type_slug);

    return true;
  }

  /**
   * Replace every item of a collection in one go, as an import does
   *
   * @param string $type_slug Collection slug
   * @param array<string, array> $entries Items keyed by entry id
   * @param string $label_field Field whose value labels an item
   * @return bool
   */
  public function replaceAll($type_slug, array $entries, $label_field = '') {
    $this->deleteAll($type_slug);

    $index = [];
    foreach ($entries as $entry_id => $entry) {
      $entry_id = (string) $entry_id;
      update_option(self::ENTRY_PREFIX . $this->key($type_slug, $entry_id), $entry, false);
      $index[$entry_id] = $this->labelFor($entry, $entry_id, $label_field);
    }

    return update_option(self::INDEX_PREFIX . $type_slug, $index, false);
  }

  /**
   * Move a collection out of the single option it used to live in
   *
   * Safe to call at any time: it does nothing once the collection has moved.
   *
   * @param string $type_slug Collection slug
   * @param string $label_field Field whose value labels an item
   * @return int How many items were moved
   */
  public function ensureMoved($type_slug, $label_field = '') {
    if (is_array(get_option(self::INDEX_PREFIX . $type_slug, null))) {
      return 0;
    }

    $legacy = $this->legacyEntries($type_slug);

    $index = [];
    foreach ($legacy as $entry_id => $entry) {
      $entry_id = (string) $entry_id;
      update_option(self::ENTRY_PREFIX . $this->key($type_slug, $entry_id), $entry, false);
      $index[$entry_id] = $this->labelFor($entry, $entry_id, $label_field);
    }

    update_option(self::INDEX_PREFIX . $type_slug, $index, false);
    delete_option(self::LEGACY_PREFIX . $type_slug);

    return count($index);
  }

  /**
   * The option name suffix for one item
   *
   * An entry id comes from an import file or from a form, so it is reduced to
   * something that is safe and stable as part of an option name. The hash keeps
   * two ids that sanitise to the same thing apart.
   *
   * @param string $type_slug Collection slug
   * @param string $entry_id Entry id
   * @return string
   */
  private function key($type_slug, $entry_id) {
    return $type_slug . '_' . substr(md5((string) $entry_id), 0, 12);
  }

  /**
   * What a collection used to keep in one option
   *
   * @param string $type_slug Collection slug
   * @return array
   */
  private function legacyEntries($type_slug) {
    $entries = get_option(self::LEGACY_PREFIX . $type_slug, []);

    return is_array($entries) ? $entries : [];
  }

  /**
   * The text that stands for an item in a list or a picker
   *
   * @param array $entry The item
   * @param string $entry_id Entry id, used when there is no usable label
   * @param string $label_field Field to read the label from
   * @return string
   */
  private function labelFor($entry, $entry_id, $label_field = '') {
    if (!is_array($entry)) {
      return $entry_id;
    }

    if ($label_field !== '' && isset($entry[$label_field]) && is_scalar($entry[$label_field])) {
      $label = trim((string) $entry[$label_field]);
      if ($label !== '') {
        return $label;
      }
    }

    // No label field named, or nothing in it: the first piece of text will do
    foreach ($entry as $value) {
      if (is_scalar($value) && trim((string) $value) !== '') {
        return trim((string) $value);
      }
    }

    return $entry_id;
  }
}
