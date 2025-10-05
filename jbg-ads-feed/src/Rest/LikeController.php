<?php
namespace JBG\Ads\Rest;
if (!defined('ABSPATH')) exit;

/**
 * واکنش کاربر به ویدیو: like / dislike / none
 *
 * POST /wp-json/jbg/v1/reaction
 * body: { ad_id:int, reaction: "like"|"dislike"|"none"|undefined }
 *
 * پاسخ: { ok:bool, reaction:string, likeCount:int, dislikeCount:int }
 *
 * نکتهٔ سازگاری:
 * - اگر reaction ارسال نشود، رفتار قدیمی toggle-like شبیه‌سازی می‌شود:
 *   اگر قبلاً like بوده → none ؛ وگرنه → like
 */
class LikeController {

    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/reaction', [
            'methods'  => 'POST',
            'callback' => [self::class, 'set_reaction'],
            'permission_callback' => function(){ return is_user_logged_in(); },
        ]);

        // مسیر قدیمی (toggle) برای سازگاری
        register_rest_route('jbg/v1', '/like', [
            'methods'  => 'POST',
            'callback' => [self::class, 'toggle_like_compat'],
            'permission_callback' => function(){ return is_user_logged_in(); },
        ]);
    }

    public static function set_reaction(\WP_REST_Request $req) {
        $uid = get_current_user_id();
        $ad  = (int)($req->get_param('ad_id') ?? 0);
        $rx  = (string)($req->get_param('reaction') ?? '');

        if ($uid <= 0) return new \WP_REST_Response(['ok'=>false,'message'=>'login'], 401);
        if ($ad <= 0 || get_post_type($ad) !== 'jbg_ad') {
            return new \WP_REST_Response(['ok'=>false,'message'=>'bad_ad'], 400);
        }
        if ($rx === '') $rx = 'auto'; // یعنی حالت سازگاری

        // واکنش‌های کاربر (نگاشت ad_id => like|dislike)
        $reactions = get_user_meta($uid, 'jbg_reactions', true);
        if (!is_array($reactions)) $reactions = [];

        // پشتیبانی از دادهٔ قدیمی: اگر قبلاً در liked_ids بود، آن را like فرض می‌کنیم
        if (!isset($reactions[$ad])) {
            $legacyLiked = (array) get_user_meta($uid, 'jbg_liked_ids', true);
            $legacyLiked = array_map('intval', $legacyLiked);
            if (in_array($ad, $legacyLiked, true)) {
                $reactions[$ad] = 'like';
            }
        }

        $prev = $reactions[$ad] ?? 'none';

        // شمارنده‌های فعلی
        $likeCount    = max(0, (int) get_post_meta($ad, 'jbg_like_count', true));
        $dislikeCount = max(0, (int) get_post_meta($ad, 'jbg_dislike_count', true));

        // اگر reaction صراحتاً تعیین نشده بود، رفتار toggle-like
        if ($rx === 'auto') {
            $rx = ($prev === 'like') ? 'none' : 'like';
        }

        // حذف اثر قبلی از شمارنده‌ها
        if ($prev === 'like'   && $likeCount    > 0) $likeCount--;
        if ($prev === 'dislike'&& $dislikeCount > 0) $dislikeCount--;

        // اعمال واکنش جدید
        switch ($rx) {
            case 'like':
                $reactions[$ad] = 'like';
                $likeCount++;
                break;
            case 'dislike':
                $reactions[$ad] = 'dislike';
                $dislikeCount++;
                break;
            default: // none
                unset($reactions[$ad]);
        }

        // ذخیره
        update_user_meta($uid, 'jbg_reactions', $reactions);
        update_post_meta($ad, 'jbg_like_count', $likeCount);
        update_post_meta($ad, 'jbg_dislike_count', $dislikeCount);

        // پاکسازی کش متا (اگر لازم)
        wp_cache_delete($ad, 'post_meta');

        return new \WP_REST_Response([
            'ok'           => true,
            'reaction'     => $rx === 'none' ? 'none' : $reactions[$ad],
            'likeCount'    => $likeCount,
            'dislikeCount' => $dislikeCount,
        ], 200);
    }

    // سازگاری با مسیر قدیمی: /jbg/v1/like  (toggle)
    public static function toggle_like_compat(\WP_REST_Request $req) {
        $req->set_param('reaction', null);
        return self::set_reaction($req);
    }
}
