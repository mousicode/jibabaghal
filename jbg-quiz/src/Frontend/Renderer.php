<?php
namespace JBG\Quiz\Frontend;

class Renderer {

    /**
     * Boot only on single Ad pages.
     */
    public static function bootstrap(): void {
        if (is_singular('jbg_ad')) {
            // Inject quiz markup after the player markup (player runs at ~5)
            add_filter('the_content', [self::class, 'inject_quiz'], 8);
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        }
    }

    /**
     * Enqueue assets and pass REST endpoints to JS.
     */
    public static function enqueue_assets(): void {
        wp_enqueue_style(
            'jbg-quiz',
            JBG_QUIZ_URL . 'assets/css/jbg-quiz.css',
            [],
            '0.1.1'
        );

        wp_enqueue_script(
            'jbg-quiz',
            JBG_QUIZ_URL . 'assets/js/jbg-quiz.js',
            [],
            '0.1.1',
            true
        );

        wp_localize_script('jbg-quiz', 'JBG_QUIZ', [
            'rest'     => rest_url('jbg/v1/quiz/submit'),   // submit answer
            'restView' => rest_url('jbg/v1/view/confirm'),  // confirm full view when Start Quiz is clicked
            'nonce'    => wp_create_nonce('wp_rest'),
            'adId'     => get_the_ID(),
        ]);
    }

    /**
     * Append the quiz block to the content if a quiz is configured.
     */
    public static function inject_quiz($content) {
        $id = get_the_ID();

        $q  = (string) get_post_meta($id, 'jbg_quiz_q',  true);
        $a1 = (string) get_post_meta($id, 'jbg_quiz_a1', true);
        $a2 = (string) get_post_meta($id, 'jbg_quiz_a2', true);
        $a3 = (string) get_post_meta($id, 'jbg_quiz_a3', true);
        $a4 = (string) get_post_meta($id, 'jbg_quiz_a4', true);

        // If quiz is not fully set, don't render anything.
        if (!$q || !$a1 || !$a2 || !$a3 || !$a4) {
            return $content;
        }

        // --- Meta needed under the quiz title: post title, brand tag, relative time
        $title = get_the_title($id);
        $brandN = wp_get_post_terms($id, 'jbg_brand', ['fields' => 'names']);
        $brand  = (!is_wp_error($brandN) && !empty($brandN)) ? $brandN[0] : '';
        $when   = trim(human_time_diff(get_the_time('U', $id), current_time('timestamp'))) . ' پیش';

        $meta  = '<div class="jbg-quiz-meta" style="margin:-6px 0 10px 0">';
        $meta .= '  <div class="jbg-quiz-meta-title" style="font-weight:700;font-size:14px;">' . esc_html($title) . '</div>';
        $meta .= '  <div class="jbg-quiz-meta-sub" style="font-size:12px;color:#4b5563;display:flex;gap:6px;align-items:center;flex-wrap:wrap">';
        if ($brand) {
            $meta .= '<span class="brand" style="background:#f1f5f9;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px;font-weight:600">'
                  .  esc_html($brand) . '</span><span class="dot" style="opacity:.55">•</span>';
        }
        $meta .= '    <span class="when">' . esc_html($when) . '</span>';
        $meta .= '  </div>';
        $meta .= '</div>';

        $html  = '<div id="jbg-quiz" class="jbg-quiz" data-ad="' . esc_attr($id) . '" style="display:none">';
        $html .= '  <div class="jbg-quiz-card">';
        $html .= '    <h3 class="jbg-quiz-title">' . esc_html__('Quiz', 'jbg-quiz') . '</h3>';
        $html .=          $meta; // ← عنوان، برند، زمان انتشار درست زیر تیتر Quiz
        $html .= '    <p class="jbg-quiz-q">' . wp_kses_post($q) . '</p>';
        $html .= '    <form id="jbg-quiz-form">';
        $html .=          self::radio('a1', $a1, 1);
        $html .=          self::radio('a2', $a2, 2);
        $html .=          self::radio('a3', $a3, 3);
        $html .=          self::radio('a4', $a4, 4);
        $html .= '      <button type="submit" class="jbg-quiz-btn">' . esc_html__('Submit', 'jbg-quiz') . '</button>';
        $html .= '    </form>';
        $html .= '    <div id="jbg-quiz-result" class="jbg-quiz-result"></div>';
        $html .= '  </div>';
        $html .= '</div>';

        return $content . $html;
    }

    /**
     * Helper to render a single radio option.
     */
    private static function radio(string $name, string $label, int $val): string {
        $id_attr = 'jbg-quiz-' . sanitize_html_class($name);
        return '<label class="jbg-quiz-opt">'
            . '<input type="radio" id="' . esc_attr($id_attr) . '" name="jbg_answer" value="' . esc_attr($val) . '"> '
            . esc_html($label)
            . '</label>';
    }
}
