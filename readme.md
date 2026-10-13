# Related Posts for WordPress - Polylang Compatibility

**Contributors:** [Teemu Suoranta](https://github.com/TeemuSuoranta), [Never5](https://www.relatedpostsforwp.com/)

**Tags:** Related Posts for WordPress, Related Posts, Polylang, WordPress

**License:** GPLv2 or later

## Description

This is an add-on to [Related Posts for WordPress](https://www.relatedpostsforwp.com) that adds compatibility to Polylang.

It keeps the related posts of a post in the language of that post. Posts of post types Polylang does not translate stay related posts of translated posts.

Requirements for the plugin:

 * Related Posts for WordPress 3.0 or newer (free or Premium), or Related Posts for WordPress Premium 2.x
 * Polylang

With Premium 3.0.1 or newer, the posts that fill up the related posts when too few are found (force fill) are in the language of the post too.

This plugin was made by Aucor and is based on the WPML compatibility plugin of Related Posts for WordPress. Version 3.0 adds support for Related Posts for WordPress 3.0.


## Installation

How-to use:

 * Install and activate this add-on
 * Done

**Composer:**
```
$ composer aucor/rp4wp-polylang-compatibility
```
**With composer.json:**
```
{
  "require": {
    "aucor/rp4wp-polylang-compatibility": "*"
  },
  "extra": {
    "installer-paths": {
      "htdocs/wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  }
}
```

## Existing links

The plugin keeps new links in the language of the post. Posts that were linked before it was activated keep their links until they are linked again: click **Rebuild everything** on the **Installer** tab of **Settings > Related Posts** (**Post Types Installer** in Premium).

## Changelog

### 3.0.0
* Works with Related Posts for WordPress 3.0 and newer, in the free plugin as well as in Premium. Premium 2.x keeps working as before.
* Posts of post types Polylang does not translate stay related posts of translated posts.
* A post that is created and published at once, such as through the REST API, an import or WP-CLI, is read and linked once Polylang has stored its language.
* With Premium 3.0.1 or newer, the posts that fill up the related posts (force fill) are in the language of the post too.

### 1.3
* The last version for Related Posts for WordPress Premium 2.x and older.
