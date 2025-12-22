<?php
/**
 * Template Name: YAML Custom Fields - Complete Example
 *
 * This template demonstrates how to output all field types
 * from the comprehensive YAML Custom Fields schema.
 */

get_header();
?>

<main id="main" class="site-main">
  <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

    <?php
    // ============================================
    // STRING FIELD
    // ============================================
    $page_title = ycf_get_field('page_title');
    if ($page_title) {
      echo '<h1>' . esc_html($page_title) . '</h1>';
    }

    // ============================================
    // TEXT FIELD (Multi-line)
    // ============================================
    $description = ycf_get_field('description');
    if ($description) {
      echo '<p>' . esc_html($description) . '</p>';
    }

    // ============================================
    // RICH TEXT FIELD (WYSIWYG)
    // ============================================
    $content = ycf_get_field('content');
    if ($content) {
      echo '<div class="page-content">' . wp_kses_post($content) . '</div>';
    }

    // ============================================
    // CODE FIELD
    // ============================================
    $custom_css = ycf_get_field('custom_css');
    if ($custom_css) {
      echo '<style>' . esc_html($custom_css) . '</style>';
    }

    $tracking_code = ycf_get_field('tracking_code');
    if ($tracking_code) {
      // Output unescaped for admin-controlled code (use with caution)
      echo $tracking_code;
    }

    // ============================================
    // BOOLEAN FIELD
    // ============================================
    $featured = ycf_get_field('featured');
    if ($featured) {
      echo '<span class="badge">Featured</span>';
    }

    // ============================================
    // NUMBER FIELD
    // ============================================
    $price = ycf_get_field('price');
    if ($price) {
      echo '<p>Price: $' . esc_html($price) . '</p>';
    }

    // ============================================
    // DATE FIELD
    // ============================================
    $event_date = ycf_get_field('event_date');
    if ($event_date) {
      echo '<p>Event Date: ' . esc_html($event_date) . '</p>';
    }

    $publish_date = ycf_get_field('publish_date');
    if ($publish_date) {
      echo '<p>Publish Date: ' . esc_html($publish_date) . '</p>';
    }

    // ============================================
    // SELECT FIELD (Single)
    // ============================================
    $category = ycf_get_field('category');
    if ($category) {
      echo '<p>Category: ' . esc_html($category) . '</p>';
    }

    // ============================================
    // SELECT FIELD (Multiple)
    // ============================================
    $topics = ycf_get_field('topics');
    if ($topics && is_array($topics)) {
      echo '<p>Topics: ' . esc_html(implode(', ', $topics)) . '</p>';
    }

    // ============================================
    // TAXONOMY FIELD (Single)
    // ============================================
    $primary_category = ycf_get_term('primary_category');
    if ($primary_category) {
      echo '<p>Category: <a href="' . esc_url(get_term_link($primary_category)) . '">' . esc_html($primary_category->name) . '</a></p>';
    }

    // ============================================
    // TAXONOMY FIELD (Multiple)
    // ============================================
    $tags = ycf_get_term('tags');
    if ($tags && is_array($tags)) {
      echo '<div class="tags">';
      foreach ($tags as $tag) {
        echo '<a href="' . esc_url(get_term_link($tag)) . '">' . esc_html($tag->name) . '</a> ';
      }
      echo '</div>';
    }

    // ============================================
    // TAXONOMY FIELD (Custom Taxonomy)
    // ============================================
    $portfolio_category = ycf_get_term('portfolio_category');
    if ($portfolio_category) {
      echo '<p>Portfolio Category: ' . esc_html($portfolio_category->name) . '</p>';
    }

    // ============================================
    // POST TYPE FIELD
    // ============================================
    $content_type = ycf_get_field('content_type');
    if ($content_type) {
      $post_type_obj = get_post_type_object($content_type);
      if ($post_type_obj) {
        echo '<p>Content Type: ' . esc_html($post_type_obj->label) . '</p>';
      }
    }

    // ============================================
    // DATA OBJECT FIELD (Single)
    // ============================================
    $university = ycf_get_data_object('university');
    if ($university) {
      echo '<div class="university">';
      echo '<h3>' . esc_html($university['name']) . '</h3>';

      if (!empty($university['logo'])) {
        echo wp_get_attachment_image($university['logo'], 'medium');
      }

      if (!empty($university['website'])) {
        echo '<p><a href="' . esc_url($university['website']) . '">Visit Website</a></p>';
      }

      if (!empty($university['description'])) {
        echo '<p>' . esc_html($university['description']) . '</p>';
      }

      if (!empty($university['location'])) {
        echo '<p>Location: ' . esc_html($university['location']) . '</p>';
      }

      if (!empty($university['founded'])) {
        echo '<p>Founded: ' . esc_html($university['founded']) . '</p>';
      }

      echo '</div>';
    }

    // ============================================
    // DATA OBJECT FIELD (List)
    // ============================================
    $partner_universities = ycf_get_field('partner_universities');
    if ($partner_universities && is_array($partner_universities)) {
      echo '<div class="partners">';
      echo '<h3>Partner Universities</h3>';

      foreach ($partner_universities as $partner_id) {
        $partner = ycf_get_data_object('partner_universities', null, ['entry_id' => $partner_id]);
        if ($partner) {
          echo '<div class="partner">';
          echo '<h4>' . esc_html($partner['name']) . '</h4>';
          if (!empty($partner['location'])) {
            echo '<p>' . esc_html($partner['location']) . '</p>';
          }
          echo '</div>';
        }
      }

      echo '</div>';
    }

    // ============================================
    // IMAGE FIELD
    // ============================================
    $hero_image = ycf_get_image('hero_image');
    if ($hero_image) {
      echo '<img src="' . esc_url($hero_image['url']) . '"
                 alt="' . esc_attr($hero_image['alt']) . '"
                 width="' . esc_attr($hero_image['width']) . '"
                 height="' . esc_attr($hero_image['height']) . '" />';
    }

    // ============================================
    // FILE FIELD
    // ============================================
    $pdf_brochure = ycf_get_file('pdf_brochure');
    if ($pdf_brochure) {
      echo '<a href="' . esc_url($pdf_brochure['url']) . '" download>';
      echo 'Download ' . esc_html($pdf_brochure['filename']);
      echo ' (' . size_format($pdf_brochure['filesize']) . ')';
      echo '</a>';
    }

    // ============================================
    // OBJECT FIELD (Nested Fields)
    // ============================================
    $author = ycf_get_field('author');
    if ($author) {
      echo '<div class="author">';

      if (!empty($author['name'])) {
        echo '<h3>' . esc_html($author['name']) . '</h3>';
      }

      if (!empty($author['email'])) {
        echo '<p>Email: <a href="mailto:' . esc_attr($author['email']) . '">' . esc_html($author['email']) . '</a></p>';
      }

      if (!empty($author['bio'])) {
        echo '<p>' . esc_html($author['bio']) . '</p>';
      }

      if (!empty($author['photo'])) {
        $photo = wp_get_attachment_image($author['photo'], 'thumbnail');
        echo $photo;
      }

      // Nested object: social_links
      if (!empty($author['social_links'])) {
        echo '<div class="social-links">';

        if (!empty($author['social_links']['twitter'])) {
          echo '<a href="' . esc_url($author['social_links']['twitter']) . '">Twitter</a> ';
        }

        if (!empty($author['social_links']['linkedin'])) {
          echo '<a href="' . esc_url($author['social_links']['linkedin']) . '">LinkedIn</a>';
        }

        echo '</div>';
      }

      echo '</div>';
    }

    // ============================================
    // BLOCK FIELD WITH ALL BLOCK TYPES
    // ============================================
    $page_sections = ycf_get_field('page_sections');
    if ($page_sections && is_array($page_sections)) {
      foreach ($page_sections as $section) {
        $block_type = isset($section['type']) ? $section['type'] : '';

        switch ($block_type) {

          // --- HERO BLOCK ---
          case 'hero':
            echo '<section class="hero">';

            if (!empty($section['title'])) {
              echo '<h2>' . esc_html($section['title']) . '</h2>';
            }

            if (!empty($section['subtitle'])) {
              echo '<p>' . esc_html($section['subtitle']) . '</p>';
            }

            if (!empty($section['background_image'])) {
              $bg_image = wp_get_attachment_image_url($section['background_image'], 'full');
              echo '<div style="background-image: url(' . esc_url($bg_image) . ');">';
            }

            if (!empty($section['overlay_opacity'])) {
              echo '<div style="opacity: ' . esc_attr($section['overlay_opacity'] / 100) . ';"></div>';
            }

            if (!empty($section['show_cta']) && !empty($section['cta_button'])) {
              echo '<a href="' . esc_url($section['cta_button']['url']) . '" class="button">';
              echo esc_html($section['cta_button']['text']);
              echo '</a>';
            }

            if (!empty($section['category'])) {
              $cat_term = get_term($section['category']);
              if ($cat_term && !is_wp_error($cat_term)) {
                echo '<span>' . esc_html($cat_term->name) . '</span>';
              }
            }

            echo '</section>';
            break;

          // --- CONTENT BLOCK ---
          case 'content_block':
            echo '<section class="content-block">';

            if (!empty($section['heading'])) {
              echo '<h2>' . esc_html($section['heading']) . '</h2>';
            }

            if (!empty($section['content'])) {
              echo '<div>' . wp_kses_post($section['content']) . '</div>';
            }

            if (!empty($section['layout'])) {
              echo '<p>Layout: ' . esc_html($section['layout']) . '</p>';
            }

            if (!empty($section['background_color'])) {
              echo '<p>Color: ' . esc_html($section['background_color']) . '</p>';
            }

            echo '</section>';
            break;

          // --- TWO COLUMN BLOCK ---
          case 'two_column':
            echo '<section class="two-column">';
            echo '<div class="row">';

            if (!empty($section['left_content'])) {
              echo '<div class="column">' . wp_kses_post($section['left_content']) . '</div>';
            }

            if (!empty($section['right_content'])) {
              echo '<div class="column">' . wp_kses_post($section['right_content']) . '</div>';
            }

            echo '</div>';
            echo '</section>';
            break;

          // --- IMAGE GALLERY BLOCK ---
          case 'gallery':
            echo '<section class="gallery">';

            if (!empty($section['title'])) {
              echo '<h2>' . esc_html($section['title']) . '</h2>';
            }

            if (!empty($section['images']) && is_array($section['images'])) {
              echo '<div class="gallery-grid">';

              foreach ($section['images'] as $gallery_item) {
                echo '<div class="gallery-item">';

                if (!empty($gallery_item['image'])) {
                  $img = wp_get_attachment_image($gallery_item['image'], 'medium');

                  if (!empty($gallery_item['link'])) {
                    echo '<a href="' . esc_url($gallery_item['link']) . '">' . $img . '</a>';
                  } else {
                    echo $img;
                  }
                }

                if (!empty($gallery_item['caption'])) {
                  echo '<p>' . esc_html($gallery_item['caption']) . '</p>';
                }

                echo '</div>';
              }

              echo '</div>';
            }

            echo '</section>';
            break;

          // --- TEAM MEMBER BLOCK ---
          case 'team_member':
            echo '<section class="team-member">';

            if (!empty($section['photo'])) {
              echo wp_get_attachment_image($section['photo'], 'medium');
            }

            if (!empty($section['name'])) {
              echo '<h3>' . esc_html($section['name']) . '</h3>';
            }

            if (!empty($section['position'])) {
              echo '<p>' . esc_html($section['position']) . '</p>';
            }

            if (!empty($section['bio'])) {
              echo '<p>' . esc_html($section['bio']) . '</p>';
            }

            if (!empty($section['email'])) {
              echo '<p><a href="mailto:' . esc_attr($section['email']) . '">' . esc_html($section['email']) . '</a></p>';
            }

            if (!empty($section['phone'])) {
              echo '<p>' . esc_html($section['phone']) . '</p>';
            }

            if (!empty($section['social_media'])) {
              echo '<div class="social">';

              if (!empty($section['social_media']['linkedin'])) {
                echo '<a href="' . esc_url($section['social_media']['linkedin']) . '">LinkedIn</a> ';
              }

              if (!empty($section['social_media']['twitter'])) {
                echo '<a href="' . esc_url($section['social_media']['twitter']) . '">Twitter</a>';
              }

              echo '</div>';
            }

            echo '</section>';
            break;

          // --- TESTIMONIAL BLOCK ---
          case 'testimonial':
            echo '<section class="testimonial">';

            if (!empty($section['quote'])) {
              echo '<blockquote>' . esc_html($section['quote']) . '</blockquote>';
            }

            if (!empty($section['author'])) {
              echo '<p><strong>' . esc_html($section['author']) . '</strong>';

              if (!empty($section['author_title'])) {
                echo ', ' . esc_html($section['author_title']);
              }

              echo '</p>';
            }

            if (!empty($section['author_photo'])) {
              echo wp_get_attachment_image($section['author_photo'], 'thumbnail');
            }

            if (!empty($section['company'])) {
              $company = ycf_get_data_object('company', null, ['entry_id' => $section['company']]);
              if ($company && !empty($company['name'])) {
                echo '<p>Company: ' . esc_html($company['name']) . '</p>';
              }
            }

            if (!empty($section['rating'])) {
              echo '<p>Rating: ' . esc_html($section['rating']) . '/5</p>';
            }

            echo '</section>';
            break;

          // --- CALL TO ACTION BLOCK ---
          case 'cta':
            echo '<section class="cta">';

            if (!empty($section['background_image'])) {
              $cta_bg = wp_get_attachment_image_url($section['background_image'], 'full');
              echo '<div style="background-image: url(' . esc_url($cta_bg) . ');">';
            }

            if (!empty($section['headline'])) {
              echo '<h2>' . esc_html($section['headline']) . '</h2>';
            }

            if (!empty($section['description'])) {
              echo '<p>' . esc_html($section['description']) . '</p>';
            }

            if (!empty($section['button_text']) && !empty($section['button_url'])) {
              echo '<a href="' . esc_url($section['button_url']) . '" class="button">';
              echo esc_html($section['button_text']);
              echo '</a>';
            }

            echo '</section>';
            break;

          // --- FAQ BLOCK ---
          case 'faq':
            echo '<section class="faq">';

            if (!empty($section['question'])) {
              echo '<h3>' . esc_html($section['question']) . '</h3>';
            }

            if (!empty($section['answer'])) {
              echo '<div>' . wp_kses_post($section['answer']) . '</div>';
            }

            if (!empty($section['category']) && is_array($section['category'])) {
              echo '<div class="faq-categories">';
              foreach ($section['category'] as $cat_id) {
                $faq_cat = get_term($cat_id);
                if ($faq_cat && !is_wp_error($faq_cat)) {
                  echo '<span>' . esc_html($faq_cat->name) . '</span> ';
                }
              }
              echo '</div>';
            }

            echo '</section>';
            break;

          // --- VIDEO BLOCK ---
          case 'video':
            echo '<section class="video">';

            if (!empty($section['title'])) {
              echo '<h2>' . esc_html($section['title']) . '</h2>';
            }

            if (!empty($section['video_url'])) {
              echo '<div class="video-embed">';
              echo esc_html($section['video_url']);
              echo '</div>';
            } elseif (!empty($section['video_file'])) {
              $video_url = wp_get_attachment_url($section['video_file']);
              if ($video_url) {
                echo '<video src="' . esc_url($video_url) . '" ';

                if (!empty($section['autoplay'])) {
                  echo 'autoplay ';
                }

                if (!empty($section['show_controls'])) {
                  echo 'controls ';
                }

                if (!empty($section['thumbnail'])) {
                  $poster_url = wp_get_attachment_image_url($section['thumbnail'], 'full');
                  echo 'poster="' . esc_url($poster_url) . '" ';
                }

                echo '></video>';
              }
            }

            echo '</section>';
            break;

          // --- CODE SNIPPET BLOCK ---
          case 'code_snippet':
            echo '<section class="code-snippet">';

            if (!empty($section['title'])) {
              echo '<h3>' . esc_html($section['title']) . '</h3>';
            }

            if (!empty($section['language'])) {
              echo '<p>Language: ' . esc_html($section['language']) . '</p>';
            }

            if (!empty($section['code'])) {
              echo '<pre><code>' . esc_html($section['code']) . '</code></pre>';
            }

            echo '</section>';
            break;

          // --- DOWNLOAD BLOCK ---
          case 'download':
            echo '<section class="download">';

            if (!empty($section['title'])) {
              echo '<h3>' . esc_html($section['title']) . '</h3>';
            }

            if (!empty($section['description'])) {
              echo '<p>' . esc_html($section['description']) . '</p>';
            }

            if (!empty($section['file'])) {
              $file_url = wp_get_attachment_url($section['file']);
              $file_name = basename(get_attached_file($section['file']));

              if ($file_url) {
                echo '<a href="' . esc_url($file_url) . '" download>';
                echo 'Download ' . esc_html($file_name);
                echo '</a>';
              }
            }

            if (!empty($section['file_type'])) {
              echo '<p>Type: ' . esc_html($section['file_type']) . '</p>';
            }

            echo '</section>';
            break;

          // --- UNIVERSITY INFO BLOCK ---
          case 'university_info':
            echo '<section class="university-info">';

            if (!empty($section['university'])) {
              $uni = ycf_get_data_object('university', null, ['entry_id' => $section['university']]);
              if ($uni) {
                echo '<h3>' . esc_html($uni['name']) . '</h3>';

                if (!empty($uni['logo'])) {
                  echo wp_get_attachment_image($uni['logo'], 'medium');
                }
              }
            }

            if (!empty($section['program'])) {
              echo '<p>Program: ' . esc_html($section['program']) . '</p>';
            }

            if (!empty($section['start_date'])) {
              echo '<p>Start Date: ' . esc_html($section['start_date']) . '</p>';
            }

            if (!empty($section['brochure'])) {
              $brochure_url = wp_get_attachment_url($section['brochure']);
              if ($brochure_url) {
                echo '<a href="' . esc_url($brochure_url) . '" download>Download Brochure</a>';
              }
            }

            echo '</section>';
            break;
        }
      }
    }
    ?>

  </article>
</main>

<?php
get_footer();
