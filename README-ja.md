# Slug-Free Permalinks

English: [README.md](README.md)

WordPress の投稿タイプとタクソノミーを、slug を使わない ID ベース permalink に切り替えるプラグインです。

## Features

- 公開かつ UI を持つ投稿タイプを個別に選択
- 公開かつ UI を持つタクソノミーを個別に選択
- `/post/123/` または `/post-123/` を選択
- 必要に応じて旧 slug URL から現在の ID URL へ 301 リダイレクト
- 設定変更時に rewrite rules を自動フラッシュ

## FAQ

**固定ページ（page）にも対応していますか？**

いいえ。固定ページは意図的に対象外にしています。多くの WordPress サイトでは固定ページに独自の URL 構造があり、スラッグを削除すると衝突が起きやすいためです。

Slug-Free Permalinks は主に投稿、カスタム投稿タイプ、タクソノミーを対象としています。

---

**すべての旧URLをリダイレクトしますか？**

いいえ。すべての 404 に対してスラッグ推測を行うような挙動はしていません。

WordPress がすでに対象コンテンツを解決できる場合のみ、旧スラッグ URL から新しい ID ベース URL へリダイレクトします。

---

**なぜ404ごとにスラッグ検索を行わないのですか？**

すべての 404 リクエストに対してスラッグ検索を行うと、不要なデータベースクエリが発生しやすく、特にボットアクセスが多い環境では負荷が増える可能性があります。

Slug-Free Permalinks はパフォーマンスと予測可能な挙動を優先した設計になっています。

---

**投稿タイプとタクソノミーのスラッグを同じにできますか？**

推奨されません。

カスタム投稿タイプとタクソノミーで同じスラッグを使うと、WordPress の rewrite ルールが衝突する可能性があります。

## Requirements

- WordPress 5.8 以上
- PHP 7.4 以上

この下限はコードで利用している PHP 構文と WordPress API に基づくものです。現時点の `readme.txt` では WordPress 6.9 までを対象にしています。

## Installation

1. このリポジトリを ZIP 化するか、`slug-free-permalinks` ディレクトリとして `/wp-content/plugins/` に配置します。
2. WordPress 管理画面の `Plugins` から有効化します。
3. `Settings > Slug-Free Permalinks` で対象の投稿タイプ・タクソノミーと URL 形式を選びます。

## Distribution

- ソースファイルはリポジトリのルートで管理します。
- `node scripts/build-dist.mjs` で `dist/slug-free-permalinks` を生成します。
- `node scripts/build-dist.mjs --zip` でバージョン付きの配布 ZIP を生成します。
- `node scripts/create-github-release.mjs` で GitHub Release を作成します。

## Notes

- 投稿タイプ slug とタクソノミー slug に同名がある場合、同じ ID パターンの rewrite rule が競合する可能性があります。
- WordPress.org 向けの配布メタデータは `readme.txt` を正本にしています。

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
