<?php
namespace JBG\Quiz\Frontend;

class Renderer {

    public static function bootstrap(): void {
        if (is_singular('jbg_ad')) {
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
            add_shortcode('jbg_quiz', [self::class, 'render_shortcode']);
        }
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style('jbg-quiz', JBG_QUIZ_URL . 'assets/css/jbg-quiz.css', [], '0.1.3');
        wp_enqueue_script('jbg-quiz', JBG_QUIZ_URL . 'assets/js/jbg-quiz.js', [], '0.1.3', true);

        // محاسبه‌ی «ویدئوی بعدی» بر اساس seq (یا menu_order)
        $nextHref  = '';
        $nextTitle = '';
        $curId     = (int) get_queried_object_id();

        if ($curId > 0) {
            $curSeq = 0;
            if (class_exists('\\JBG\\Ads\\Progress\\Access')) {
                $curSeq = \JBG\Ads\Progress\Access::seq($curId);
            }

            $q = new \WP_Query([
                'post_type'           => 'jbg_ad',
                'post_status'         => 'publish',
                'posts_per_page'      => -1,
                'fields'              => 'ids',
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
                'suppress_filters'    => true,
                'orderby'             => ['menu_order'=>'ASC','date'=>'ASC','ID'=>'ASC'],
                'lang'                => 'all',
            ]);

            $bestId  = 0;
            $bestSeq = PHP_INT_MAX;

            if ($q->have_posts()) {
                foreach ($q->posts as $pid) {
                    $s = 0;
                    if (class_exists('\\JBG\\Ads\\Progress\\Access')) {
                        $s = \JBG\Ads\Progress\Access::seq((int)$pid);
                    } else {
                        $s = (int) get_post_field('menu_order', (int)$pid);
                        if ($s <= 0) $s = 1;
                    }
                    if ($s > $curSeq && $s < $bestSeq) {
                        $bestSeq = $s;
                        $bestId  = (int) $pid;
                    }
                }
            }
            wp_reset_postdata();

            if ($bestId) {
                $nextHref  = get_permalink($bestId);
                $nextTitle = get_the_title($bestId);
            }
        }

        wp_localize_script('jbg-quiz', 'JBG_QUIZ', [
            'rest'      => rest_url('jbg/v1/quiz/submit'),
            'restView'  => rest_url('jbg/v1/view/confirm'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'adId'      => get_the_ID(),
            'nextHref'  => $nextHref,
            'nextTitle' => $nextTitle,
        ]);
    }

    /** شورتکد: [jbg_quiz] */
    public static function render_shortcode($atts = []) {
        $id = get_the_ID();

        $q  = (string) get_post_meta($id, 'jbg_quiz_q',  true);
        $a1 = (string) get_post_meta($id, 'jbg_quiz_a1', true);
        $a2 = (string) get_post_meta($id, 'jbg_quiz_a2', true);
        $a3 = (string) get_post_meta($id, 'jbg_quiz_a3', true);
        $a4 = (string) get_post_meta($id, 'jbg_quiz_a4', true);

        // اگر کوییز کامل نیست، خروجی خالی بده
        if (!$q || !$a1 || !$a2 || !$a3 || !$a4) return '';

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
        // دکمه ویدئوی بعدی (ابتدا مخفی؛ JS پرش می‌کند)
        $html .= '    <div id="jbg-next-wrap" style="margin-top:10px">';
        $html .= '      <a id="jbg-next-btn" class="jbg-btn" style="display:none"></a>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';

        return $html;
    }

    private static function radio(string $name, string $label, int $val): string {
        $id_attr = 'jbg-quiz-' . sanitize_html_class($name);
        return '<label class="jbg-quiz-opt">'
             . '<input type="radio" id="' . esc_attr($id_attr) . '" name="jbg_answer" value="' . esc_attr($val) . '"> '
             . esc_html($label)
             . '</label>';
    }
}
