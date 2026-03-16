# Slug-Free Permalinks

WordPress の投稿タイプとタクソノミーを、slug 管理なしの ID ベース permalink に切り替えるプラグインです。

## 配布ファイル
- [slug-free-permalinks.php](/C:/dev/slug-free-permalinks/slug-free-permalinks.php)
- [readme.txt](/C:/dev/slug-free-permalinks/readme.txt)
- [uninstall.php](/C:/dev/slug-free-permalinks/uninstall.php)

## 概要
- 公開かつ UI を持つ投稿タイプを個別に選択
- 公開かつ UI を持つタクソノミーを個別に選択
- `/post/123/` または `/post-123/` を選択
- 必要なら旧 slug URL から現在の ID URL へ 301 リダイレクト

## ローカル確認
既存の PHP lint スクリプトがある環境から実行する場合:

```powershell
node -e "import('C:/dev/umami/theme/scripts/php-lint.mjs').then(({ runPhpLint }) => { const result = runPhpLint({ cwd: 'C:/dev/slug-free-permalinks' }); if (!result.ok) { console.error(result.summary); if (result.detail) console.error(result.detail); process.exit(1); } console.log(result.summary); });"
```

## 開発メモ
- 配布用の正本はリポジトリ直下に置く
- メインファイル名は plugin slug に合わせて `slug-free-permalinks.php`
- WordPress.org 向けの説明は [readme.txt](/C:/dev/slug-free-permalinks/readme.txt) を正本にする
