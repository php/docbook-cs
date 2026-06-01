import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const config: Config = {
  title: 'DocbookCS',
  tagline: 'A static-analysis linter for DocBook XML',
  favicon: 'img/favicon.ico',

  url: 'https://php.github.io',
  baseUrl: '/docbook-cs/',

  organizationName: 'php',
  projectName: 'docbook-cs',
  trailingSlash: false,

  onBrokenLinks: 'throw',

  markdown: {
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          editUrl: 'https://github.com/php/docbook-cs/tree/gh-pages/',
          routeBasePath: '/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    colorMode: {
      defaultMode: 'light',
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'DocbookCS',
      items: [
        {
          href: 'https://github.com/php/docbook-cs',
          label: 'GitHub',
          position: 'right',
        },
        {
          href: 'https://packagist.org/packages/php/docbook-cs',
          label: 'Packagist',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'light',
      links: [
        {
          title: 'Docs',
          items: [
            {label: 'Introduction', to: '/'},
            {label: 'Installation', to: '/installation'},
            {label: 'Configuration', to: '/configuration'},
            {label: 'Sniffs', to: '/sniffs'},
          ],
        },
        {
          title: 'Links',
          items: [
            {label: 'GitHub', href: 'https://github.com/php/docbook-cs'},
          ],
        },
      ],
      copyright: `Copyright © ${new Date().getFullYear()} Jordi Kroon. Apache 2.0 licensed.`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['php', 'bash', 'json', 'yaml'],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
