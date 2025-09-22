<?php
namespace JBG\Quiz\Frontend;

class Renderer {

    /**
     * Boot on single Ad pages.
     */
    public static function bootstrap(): void {
        if (is_singular('jbg_ad')) {
            // فقط استایل/اسکریپت را لود می‌کنیم
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
            // شورتکد برای نمایش آزمون هرجا که بخواهیم
            add_shortcode('jbg_quiz', [self::class, 'render_shortcode']);
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
            '0.1.2'
        );

        wp_enqueue_script(
            'jbg-quiz',
            JBG_QUIZ_URL . 'assets/js/jbg-quiz.js',
            [],
            '0.1.2',
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
     * Shortcode renderer: [jbg_quiz]
     * (بدون عنوان/برند/زمان؛ فقط خود آزمون)
     */
    public static function render_shortcode($atts = []) {
        $id = get_the_ID();

        $q  = (string) get_post_meta($id, 'jbg_quiz_q',  true);
        $a1 = (string) get_post_meta($id, 'jbg_quiz_a1', true);
        $a2 = (string) get_post_meta($id, 'jbg_quiz_a2', true);
        $a3 = (string) get_post_meta($id, 'jbg_quiz_a3', true);
        $a4 = (string) get_post_meta($id, 'jbg_quiz_a4', true);

        // اگر کوییز کامل تنظیم نشده، چیزی نشان نده
        if (!$q || !$a1 || !$a2 || !$a3 || !$a4) {
            return '';
        }

        $html  = '<div id="jbg-quiz" class="jbg-quiz" data-ad="' . esc_attr($id) . '" style="display:none">';
        $html .= '  <div class="jbg-quiz-card">';
        $html .= '    <h3 class="jbg-quiz-title">' . esc_html__('Quiz', 'jbg-quiz') . '</h3>';
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

        return $html;
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
