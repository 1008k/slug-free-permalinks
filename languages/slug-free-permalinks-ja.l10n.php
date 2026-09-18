<?php

if (! defined('ABSPATH')) {
    exit;
}

return array(
    'x-generator' => 'slug-free-permalinks',
    'translation-revision-date' => '2026-09-18 00:00+0000',
    'plural-forms' => 'nplurals=1; plural=0;',
    'project-id-version' => 'Slug-Free Permalinks 1.5.1',
    'language' => 'ja',
    'messages' => array(
        'Slug-Free Permalinks' => 'Slug-Free Permalinks',
        'Use ID based permalinks for selected post types and taxonomies without managing slugs.' => '選択した投稿タイプとタクソノミーで、スラッグを使わないIDベースのパーマリンクを利用できます。',
        'Settings' => '設定',
        'Checked post types and taxonomies will use the selected ID based permalink format. Clear all checks to disable. Rewrite rules are flushed automatically when settings change.' => 'チェックした投稿タイプとタクソノミーには、選択したIDベースのパーマリンク形式が適用されます。すべてのチェックを外すと無効になります。設定変更時にはリライトルールが自動的にフラッシュされます。',
        'Permalink format' => 'パーマリンク形式',
        'Target post types' => '対象投稿タイプ',
        'Target taxonomies' => '対象タクソノミー',
        'Redirect legacy permalinks' => '旧パーマリンクをリダイレクト',
        'Redirect old slug based URLs to the current ID based permalink when WordPress can resolve the request.' => 'WordPressがリクエストを解決できる場合、古いスラッグベースURLを現在のIDベースのパーマリンクへリダイレクトします。',\n        'Before deactivating' => '無効化する前に',\n        'To keep the current ID URL format for regular posts, set the following value as the Custom Structure under Settings > Permalinks before deactivating this plugin.' => '通常投稿で現在のID URL形式を維持するには、このプラグインを無効化する前に、以下の値を「設定 > パーマリンク」のカスタム構造へ設定してください。',\n        'WordPress custom structure' => 'WordPressのカスタム構造',\n        'Copy' => 'コピー',\n        'Open Permalink Settings' => 'パーマリンク設定を開く',\n        'Custom post types and taxonomies are not controlled by the regular WordPress post permalink setting. Check their rewrite settings before deactivating the plugin.' => 'カスタム投稿タイプとタクソノミーは、WordPress標準の投稿パーマリンク設定では管理されません。プラグインを無効化する前に、それぞれのrewrite設定を確認してください。',
    ),
);
