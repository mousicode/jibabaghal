<?php
namespace JBG\Quiz;


use JBG\Quiz\Admin\MetaBox;
use JBG\Quiz\Frontend\Renderer;
use JBG\Quiz\Rest\QuizController;


class Bootstrap {
public static function init(): void {
add_action('add_meta_boxes', [MetaBox::class, 'register']);
add_action('save_post_jbg_ad', [MetaBox::class, 'save'], 10, 2);


add_action('wp', [Renderer::class, 'bootstrap']);
add_action('rest_api_init', [QuizController::class, 'register_routes']);
}


public static function activate(): void { }
public static function deactivate(): void { }
}