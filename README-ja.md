# Slug-Free Permalinks

English: [README.md](README.md)

WordPress の投稿タイプとタクソノミーを、slug を使わない ID ベース permalink に切り替えるプラグインです。

## なぜこのプラグインが必要か

WordPress の slug は便利ですが、日々の運用では地味に面倒なことがあります。

このプラグインは、たとえば次のような悩みがあるサイト向けです。

- 記事を書いたり、カテゴリやタグを増やすたびに slug を意識したくない
- 2バイト文字が URL エンコードされて、URL が長く見づらくなるのを避けたい
- 後からタイトルを変更したときに、タイトルと slug がチグハグになるのを防ぎたい

Slug-Free Permalinks は、必要な投稿タイプとタクソノミーだけを対象にしつつ、タイトルや言語依存の slug ではなく、安定した ID ベースの URL へ切り替えます。

## Features

- 公開かつ UI を持つ投稿タイプを個別に選択
- 公開かつ UI を持つタクソノミーを個別に選択
- `/post/123/` または `/post-123/` を選択
- 必要に応じて旧 slug URL から現在の ID URL へ 301 リダイレクト
- Polylang などが追加した `/en/` のような言語・パスプレフィックスを維持
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

---

**Polylang や `/en/` のような言語ディレクトリ付き URL でも使えますか？**

はい。正規の ID ベース URL 自体は常にサイトホーム基準で作られ、その上に言語ディレクトリ系プラグインのプレフィックスが付く形になります。

たとえば基本形は `/post/123/` のままで、Polylang のような構成では `/en/post/123/` や `/en/category/45/` として利用できます。

## Requirements

- WordPress 5.8 以上
- PHP 7.4 以上

この下限はコードで利用している PHP 構文と WordPress API に基づくものです。現時点の `readme.txt` では WordPress 6.9 までを対象にしています。

## Installation

1. WordPress 管理画面で `Plugins > Add New` を開きます。
2. `Slug-Free Permalinks` を検索します。
3. `Install Now` をクリックし、その後有効化します。
4. `Settings > Slug-Free Permalinks` で対象の投稿タイプ・タクソノミーと URL 形式を選びます。

手動インストールする場合は、`slug-free-permalinks` ディレクトリを `/wp-content/plugins/` に配置してから `Plugins` 画面で有効化してください。

## Distribution

- ソースファイルはリポジトリのルートで管理します。
- `dist/` はローカルのビルド生成物で、Git では追跡しません。
- `node scripts/build-dist.mjs` で `dist/slug-free-permalinks` を生成します。
- `node scripts/build-dist.mjs --zip` でバージョン付きの配布 ZIP を生成します。
- GitHub Actions は `dist/slug-free-permalinks` に対して、PR 時と `main` への push 時に Plugin Check を実行します。
- `1.4.4` のような Git タグを push すると、GitHub Actions から WordPress.org へ自動デプロイされます。
- deploy workflow は `X.Y.Z` 形式のタグだけを受け付けます。
- workflow は Git タグ、`slug-free-permalinks.php` の `Version:`、`readme.txt` の `Stable tag:` が完全一致していることを検証します。
- `node scripts/create-github-release.mjs` も同じバージョンタグ形式を使います。

## WordPress.org Assets

- WordPress.org 用の任意 assets は `.wordpress-org/` に置きます。
- よく使うファイル名は `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`, `screenshot-1.png` です。
- deploy workflow は、これらのファイルが存在すれば `.wordpress-org/` を WordPress.org の `assets/` に同期します。

## Notes

- 投稿タイプ slug とタクソノミー slug に同名がある場合、同じ ID パターンの rewrite rule が競合する可能性があります。
- WordPress.org 向けの配布メタデータは `readme.txt` を正本にしています。
- WordPress.org への配布対象はリポジトリ直下ではなく `dist/slug-free-permalinks` です。
- WordPress.org のアイコン、バナー、スクリーンショットは任意で、必要になったら `.wordpress-org/` に追加できます。

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
