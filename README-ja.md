# Slug-Free Permalinks

English: [README.md](README.md)

WordPress の投稿タイプとタクソノミーを、slug を使わない ID ベース permalink に切り替えるプラグインです。

## Features

- 公開かつ UI を持つ投稿タイプを個別に選択
- 公開かつ UI を持つタクソノミーを個別に選択
- `/post/123/` または `/post-123/` を選択
- 必要に応じて旧 slug URL から現在の ID URL へ 301 リダイレクト
- 設定変更時に rewrite rules を自動フラッシュ

## Requirements

- WordPress 5.8 以上
- PHP 7.4 以上

この下限はコードで利用している PHP 構文と WordPress API に基づくものです。現時点の `readme.txt` では WordPress 6.9 までを対象にしています。

## Installation

1. このリポジトリを ZIP 化するか、`slug-free-permalinks` ディレクトリとして `/wp-content/plugins/` に配置します。
2. WordPress 管理画面の `Plugins` から有効化します。
3. `Settings > Slug-Free Permalinks` で対象の投稿タイプ・タクソノミーと URL 形式を選びます。

## Notes

- 投稿タイプ slug とタクソノミー slug に同名がある場合、同じ ID パターンの rewrite rule が競合する可能性があります。
- WordPress.org 向けの配布メタデータは `readme.txt` を正本にしています。

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
