<?php

if (! defined('ABSPATH')) {
    exit;
}

return array(
    'x-generator' => 'slug-free-permalinks',
    'translation-revision-date' => '2026-03-17 00:00+0000',
    'plural-forms' => 'nplurals=1; plural=0;',
    'project-id-version' => 'Slug-Free Permalinks 1.4.2',
    'language' => 'ja',
    'messages' => array(
        'Slug-Free Permalinks' => 'Slug-Free Permalinks',
        'Use ID based permalinks for selected post types and taxonomies without managing slugs.' => '選択した投稿タイプとタクソノミーを、slug 管理なしの ID ベース permalink に切り替えます。',
        'Settings' => '設定',
        'Checked post types and taxonomies will use the selected ID based permalink format. Clear all checks to disable. Rewrite rules are flushed automatically when settings change.' => 'チェックした投稿タイプとタクソノミーで、選択した ID ベース permalink 形式を使用します。すべてのチェックを外すと無効になります。設定変更時には rewrite ルールが自動でフラッシュされます。',
        'Permalink format' => 'Permalink 形式',
        'Target post types' => '対象の投稿タイプ',
        'Target taxonomies' => '対象のタクソノミー',
        'Redirect legacy permalinks' => '旧 permalink をリダイレクト',
        'Redirect old slug based URLs to the current ID based permalink when WordPress can resolve the request.' => 'WordPress がリクエストを解決できる場合、旧 slug ベース URL を現在の ID ベース permalink へリダイレクトします。',
    ),
);
