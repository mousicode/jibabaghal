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

        $nextHref  = '';
        $nextTitle = '';
        $curId     = (int) get_queried_object_id();

        if ($curId > 0 && class_exists('\\JBG\\Ads\\Progress\\Access')) {
            $nextId = \JBG\Ads\Progress\Access::next_ad_id($curId);
            if ($nextId) {
                $nextHref  = get_permalink($nextId);
                $nextTitle = get_the_title($nextId);
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

    /* ... بقیه بدون تغییر ... */
}
