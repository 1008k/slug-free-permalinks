<?php

if (! defined('ABSPATH')) {
    exit;
}

return array(
    'x-generator' => 'slug-free-permalinks',
    'translation-revision-date' => '2026-09-18 00:00+0000',
    'plural-forms' => 'nplurals=1; plural=0;',
    'project-id-version' => 'Slug-Free Permalinks 2.0.0',
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
        'Redirect old slug based URLs to the current ID based permalink when WordPress can resolve the request.' => 'WordPressがリクエストを解決できる場合、古いスラッグベースURLを現在のIDベースのパーマリンクへリダイレクトします。',
        'Before deactivating' => '無効化する前に',
        'To keep the current ID URL format for regular posts, set the following value as the Custom Structure under Settings > Permalinks before deactivating this plugin.' => '通常投稿で現在のID URL形式を維持するには、このプラグインを無効化する前に、以下の値を「設定 > パーマリンク」のカスタム構造へ設定してください。',
        'WordPress custom structure' => 'WordPressのカスタム構造',
        'Copy' => 'コピー',
        'Open Permalink Settings' => 'パーマリンク設定を開く',
        'Custom post types and taxonomies are not controlled by the regular WordPress post permalink setting. Check their rewrite settings before deactivating the plugin.' => 'カスタム投稿タイプとタクソノミーは、WordPress標準の投稿パーマリンク設定では管理されません。プラグインを無効化する前に、それぞれのrewrite設定を確認してください。',
        'Helpful links' => '参考リンク',
        'Official site' => '公式サイト',
        'See the feature overview, installation steps, FAQ, and recent changes.' => '機能概要、インストール手順、FAQ、更新履歴を確認できます。',
        'Visit official site' => '公式サイトを見る',
        'Introduction article' => '紹介記事',
        'Read why the plugin uses ID-based URLs and how the design works.' => 'IDベースURLを採用した理由や設計方針を紹介しています。',
        'Read article' => '記事を読む',
        'Share feedback' => 'フィードバック',
        'If the plugin helps your site, feedback on WordPress.org is appreciated.' => 'プラグインが役立ったら、WordPress.orgでの評価やレビューを歓迎しています。',
        'Open reviews' => 'レビューを見る',
        '{type} is replaced with the post type or taxonomy rewrite slug.' => '{type} は投稿タイプまたはタクソノミーの rewrite slug に置き換わります。',
        ' Example: /post/123/ or /post-123/.' => ' 例: /post/123/ または /post-123/。',
        'Regular posts structure' => '通常投稿の構造',
        'Keep the WordPress permalink structure for regular posts and replace only the slug with the post ID.' => '通常投稿では WordPress のパーマリンク構造を維持し、スラッグ部分だけを投稿 ID に置き換えます。',
        'Applies only when Post is selected as a target post type. The permalink format above does not apply to regular posts while this is on. Example: with /%year%/%monthnum%/%postname%/, a post URL becomes /2026/10/123/.' => '「投稿」が対象投稿タイプに選択されている場合にのみ適用されます。これが有効な間、上のパーマリンク形式は通常投稿には適用されません。例: /%year%/%monthnum%/%postname%/ の場合、投稿 URL は /2026/10/123/ になります。',
        'The current WordPress permalink structure does not contain %postname%, so this option currently has no effect.' => '現在の WordPress のパーマリンク構造には %postname% が含まれていないため、このオプションは現在効果がありません。',
    ),
);
