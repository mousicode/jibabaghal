<?php
namespace JBG\Quiz\Frontend;

class Renderer {

    public static function bootstrap(): void {
        add_shortcode('jbg_quiz', [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void {
        if (!is_singular('jbg_ad')) return;

        $ver = '0.1.6';

        wp_enqueue_style('jbg-quiz', JBG_QUIZ_URL . 'assets/css/jbg-quiz.css', [], $ver);
        wp_enqueue_script('jbg-quiz', JBG_QUIZ_URL . 'assets/js/jbg-quiz.js', [], $ver, true);

        $curId = (int) get_queried_object_id();

        $nextHref = ''; $nextTitle = '';
        if ($curId > 0 && class_exists('\\JBG\\Ads\\Progress\\Access')) {
            $nextId = \JBG\Ads\Progress\Access::next_ad_id($curId);
            if ($nextId) {
                $nextHref  = get_permalink($nextId);
                $nextTitle = get_the_title($nextId);
            }
        }

        $points = (int) get_post_meta($curId, 'jbg_points', true);

        wp_localize_script('jbg-quiz', 'JBG_QUIZ', [
            'rest'      => rest_url('jbg/v1/quiz/submit'),
            'restView'  => rest_url('jbg/v1/view/confirm'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'adId'      => $curId,
            'nextHref'  => $nextHref,
            'nextTitle' => $nextTitle,
            'points'    => $points,
        ]);
    }

    public static function render_shortcode($atts = []){
        $id = get_the_ID();
        $q  = (string) get_post_meta($id, 'jbg_quiz_q',  true);
        $a1 = (string) get_post_meta($id, 'jbg_quiz_a1', true);
        $a2 = (string) get_post_meta($id, 'jbg_quiz_a2', true);
        $a3 = (string) get_post_meta($id, 'jbg_quiz_a3', true);
        $a4 = (string) get_post_meta($id, 'jbg_quiz_a4', true);
        if (!$q || !$a1 || !$a2 || !$a3 || !$a4) return '';

        $h  = '<div id="jbg-quiz" class="jbg-quiz" data-ad="'.esc_attr($id).'" style="display:none">';
        $h .= '  <div class="jbg-quiz-card">';

        // قبلاً: عنوان ثابت Quiz + پاراگراف سؤال
        // $h .= '    <h3 class="jbg-quiz-title">'.esc_html__("Quiz","jbg-quiz").'</h3>';
        // $h .= '    <p class="jbg-quiz-q">'.wp_kses_post($q).'</p>';

        // جدید: خودِ متن سؤال به عنوان تیتر
        $h .= '    <h3 class="jbg-quiz-title">'.wp_kses_post($q).'</h3>';

        $h .= '    <form id="jbg-quiz-form">';
        $h .=        self::radio('a1',$a1,1).self::radio('a2',$a2,2).self::radio('a3',$a3,3).self::radio('a4',$a4,4);

        // قبلاً: دکمه Submit بدون id
        // $h .= '      <button type="submit" class="jbg-quiz-btn">'.esc_html__("Submit","jbg-quiz").'</button>';

        // جدید: متن فارسی + id ثابت برای استایل‌دهی
        $h .= '      <button type="submit" id="jbg-quiz-btn" class="jbg-quiz-btn jbg-btn">ثبت</button>';

        $h .= '    </form>';
        $h .= '    <div id="jbg-quiz-result" class="jbg-quiz-result" style="margin-top:8px"></div>';
        $h .= '    <div id="jbg-next-wrap" style="margin-top:10px"><a id="jbg-next-btn" class="jbg-btn" style="display:none"></a></div>';
        $h .= '  </div></div>';
        return $h;
    }

    private static function radio(string $name, string $label, int $val): string {
        $id = 'jbg-quiz-' . sanitize_html_class($name);
        return '<label class="jbg-quiz-opt"><input type="radio" id="'.esc_attr($id).'" name="jbg_answer" value="'.esc_attr($val).'"> '.esc_html($label).'</label>';
    }
}
