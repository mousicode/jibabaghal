<?php
namespace JBG\Player\Admin;

if (!defined('ABSPATH')) exit;

class MetaBox {

    // کلید متای ویدیو (همان کلید قبلی را نگه می‌داریم)
    const META_KEY = 'jbg_video_src';

    public static function register(): void {
        add_meta_box(
            'jbg_video_source',
            __('Video Source', 'jbg-player'),
            [self::class, 'render'],
            'jbg_ad',
            'normal',
            'default'
        );

        // ذخیره
        add_action('save_post_jbg_ad', [self::class, 'save'], 10, 2);

        // اسکریپت و رسانه فقط در ویرایش jbg_ad
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
    }

    public static function assets($hook): void {
        // فقط صفحه افزودن/ویرایش پست و فقط برای jbg_ad
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'jbg_ad') return;

        // مدیالایبرری وردپرس
        wp_enqueue_media();

        // اسکریپت کوچک برای دکمه‌ها
        $js = <<<JS
        (function($){
            $(document).on('click', '#jbg-video-select', function(e){
                e.preventDefault();
                var frame = wp.media({
                    title: 'انتخاب یا آپلود ویدیو',
                    library: { type: ['video'] },
                    button: { text: 'استفاده از این ویدیو' },
                    multiple: false
                });
                frame.on('select', function(){
                    var file = frame.state().get('selection').first().toJSON();
                    // URL فایل ویدیو
                    $('#jbg-video-src').val(file.url).trigger('change');
                    // پیش‌نمایش نام فایل
                    $('#jbg-video-filename').text(file.filename || file.url);
                });
                frame.open();
            });

            $(document).on('click', '#jbg-video-clear', function(e){
                e.preventDefault();
                $('#jbg-video-src').val('');
                $('#jbg-video-filename').text('—');
            });
        })(jQuery);
        JS;

        wp_add_inline_script('jquery', $js);
        $css = <<<CSS
        #jbg-video-src{width:100%}
        .jbg-video-meta-row{display:flex;gap:8px;align-items:center;margin-top:8px}
        .jbg-video-help{opacity:.75;font-size:12px;margin-top:6px}
        .button.jbg-pill{border-radius:9999px;padding-inline:14px}
        CSS;
        wp_add_inline_style('wp-admin', $css);
    }

    public static function render(\WP_Post $post): void {
        $val = (string) get_post_meta($post->ID, self::META_KEY, true);
        wp_nonce_field('jbg_video_src_nonce', 'jbg_video_src_nonce');

        echo '<p class="jbg-video-help">'
            . esc_html__('Provide MP4 or HLS (m3u8) URL. HLS is recommended', 'jbg-player')
            . '</p>';

        // فیلد URL را نگه می‌داریم تا اگر ادمین خواست دستی هم وارد کند.
        echo '<div class="jbg-video-meta-row">';
        echo    '<input type="url" id="jbg-video-src" name="'.esc_attr(self::META_KEY).'" value="' . esc_attr($val) . '" placeholder="https://.../video.mp4" />';
        echo    '<a href="#" id="jbg-video-select" class="button button-primary jbg-pill">افزودن رسانه / انتخاب ویدیو</a>';
        echo    '<a href="#" id="jbg-video-clear" class="button jbg-pill">حذف</a>';
        echo '</div>';

        echo '<p class="jbg-video-help">فایل انتخاب‌شده: <strong id="jbg-video-filename">'
            . ($val ? esc_html(wp_basename($val)) : '—')
            . '</strong></p>';
    }

    public static function save(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['jbg_video_src_nonce']) || !wp_verify_nonce($_POST['jbg_video_src_nonce'], 'jbg_video_src_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'jbg_ad') return;
        if (!current_user_can('edit_post', $post_id)) return;

        $url = isset($_POST[self::META_KEY]) ? esc_url_raw(trim((string)$_POST[self::META_KEY])) : '';
        if ($url) {
            update_post_meta($post_id, self::META_KEY, $url);
        } else {
            delete_post_meta($post_id, self::META_KEY);
        }
    }
}
