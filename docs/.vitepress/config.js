import baseConfig from '@cakephp/docs-skeleton/config'

import { createRequire } from "module";
const require = createRequire(import.meta.url);
const toc_en = require("./toc_en.json");

const versions = {
  text: "4.x",
  items: [
    { text: "4.x (current)", link: "https://book.cakephp.org/authentication/4/", target: '_self' },
    { text: "3.x", link: "https://book.cakephp.org/authentication/3/en/", target: '_self' },
    { text: "2.x", link: "https://book.cakephp.org/authentication/2/en/", target: '_self' },
  ],
};

// This file contains overrides for .vitepress/config.js
export default {
  extends: baseConfig,
  srcDir: 'en',
  title: 'Authentication plugin',
  description: 'Authentication - CakePHP Authentication Plugin Documentation',
  base: "/authentication/4/",
  rewrites: {
    "en/:slug*": ":slug*",
  },
  sitemap: {
    hostname: "https://book.cakephp.org/authentication/4/",
  },
  themeConfig: {
    siteTitle: false,
    pluginName: "Authentication",
    socialLinks: [
      { icon: "github", link: "https://github.com/cakephp/authentication" },
    ],
    editLink: {
      pattern: "https://github.com/cakephp/authentication/edit/4.x/docs/:path",
      text: "Edit this page on GitHub",
    },
    sidebar: toc_en,
    nav: [
      { text: "CakePHP Book", link: "https://book.cakephp.org/" },
      { ...versions },
    ],
  },
  substitutions: {},
  locales: {
    root: {
      label: "English",
      lang: "en",
    },
  },
};
