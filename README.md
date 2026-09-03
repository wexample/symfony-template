# symfony_template

Version: 0.0.28

`wexample/symfony-template` is a Symfony bundle that adds templating helpers to a Twig-based application: an `icon()` / `icon_source()` / `icon_list()` set that resolves names like `fa:solid/coffee` across Font Awesome, Material Icons and Phosphor — as a tag or as inlined, class-annotated SVG — a `markdown` filter backed by `league/commonmark`, and a `system_version()` function reading the project's `version.txt`. Alongside them, `Wexample\SymfonyTemplate\Helper\TemplateHelper` handles the string work around views: stripping the `.html.twig` extension, trimming the `@` namespace prefix, turning a controller namespace into snake-cased path parts.

It is meant for Symfony developers building on the Wexample suite — it requires `wexample/symfony-helpers` and registers itself through `WexampleSymfonyTemplateBundle`, which also exposes the bundle's `assets/` directory to the front-end loader.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The package is a Symfony bundle with no controllers, no entities and no configuration tree: it registers a handful of Twig extensions, and those extensions delegate to services. Four layers, in the order a request crosses them — bundle → container → Twig extension → service → icon manager — plus one static helper that sits outside the container entirely.

### Bundle and container wiring

src/WexampleSymfonyTemplateBundle.php extends `AbstractBundle` from `wexample/symfony-helpers` and implements `LoaderBundleInterface`. Its only body is the front-asset declaration:

```php
public static function getLoaderFrontPaths(): array
{
    return [
        BundleHelper::getBundleCssAlias(static::class) => __DIR__.'/../assets/',
    ];
}
```

`getBundleCssAlias()` kebab-cases the first two namespace segments, so the alias is `@wexample/symfony-template` pointing at assets.

Service registration goes through src/DependencyInjection/WexampleSymfonyTemplateExtension.php, whose `load()` is a single call to `$this->loadConfig(__DIR__, $container)`. The parent resolves `__DIR__.'/../Resources/config'` and loads `services.yaml` — which is why src/Resources/config/services.yaml lives under `Resources/`, not at the package root. That file registers two directories and nothing else:

```yaml
Wexample\SymfonyTemplate\:
    resource: '../../{Service,Twig}'
    tags: ['controller.service_arguments']
```

`src/Helper/` is deliberately absent from that glob: `TemplateHelper` is all static methods and is never instantiated.

### The Twig layer

Three extensions in src/Twig, all extending `AbstractExtension` from `wexample/symfony-helpers` for the `FUNCTION_OPTION_IS_SAFE` / `FUNCTION_OPTION_NEEDS_ENVIRONMENT` constants.

- src/Twig/IconExtension.php exposes `icon()`, `icon_source()` and `icon_list()`. It owns no logic — each method forwards to `IconService` with the same arguments. Note that the function name for the first is built as `VariableHelper::ICON . '_source'`, and the callable as `VariableHelper::ICON . 'Source'`.
- src/Twig/MarkdownExtension.php owns a `markdown` filter and its own `CommonMarkConverter`, built in the constructor with `'html_input' => 'strip'` and `'allow_unsafe_links' => false`. No service, no manager: the converter is the whole implementation.
- src/Twig/SystemExtension.php exposes `system_version()`, which reads `$this->kernel->getProjectDir() . '/' . $versionFile` (default `version.txt`) and returns `null` when the file is absent. The file belongs to the host application, not to this package.

### Icon resolution

src/Service/IconService.php is the dispatcher. It takes the three managers by constructor injection and never touches the filesystem itself. Every entry point starts by splitting the name on `AbstractIconManager::LIBRARY_SEPARATOR`, which is `':'`:

```php
[$prefix, $icon] = array_pad(explode(AbstractIconManager::LIBRARY_SEPARATOR, $name, 2), 2, '');
```

`icon()` then tries the managers in a fixed order — Phosphor, Material, Font Awesome — comparing `$lib = $type ?? $prefix` against each `getLibraryKey()` (`ph`, `material`, `fa`). Phosphor is checked first and unconditionally, since it needs no index. Material and Font Awesome are additionally gated on `hasIcon($icon)`. When nothing matches, the service returns a bare `<i class="icon">` containing the raw name, so an unresolved icon degrades to visible text rather than to an exception.

Two things an agent editing this file should know before changing behaviour. The docblock advertises auto-detection for unprefixed names, but the `$lib === null` branches guarding it are unreachable: `array_pad` fills with `''`, and an unprefixed `"coffee"` yields `$prefix = 'coffee'`, `$icon = ''`. And `iconSource()` returns inline SVG for Material and Font Awesome only — Phosphor and the default case both return `DomHelper::buildTag('span')`, an empty tag, because Phosphor ships glyphs as a webfont with no SVG files to inline.

### Icon managers and their index

src/Service/Icon/AbstractIconManager.php owns everything the three libraries share and leaves three abstract methods: `getLibraryKey()`, `buildIconsList()` and `iconTag()`.

The index is built in the constructor, not lazily. On a cache miss it calls `buildIconsList()` and saves immediately; on a hit it restores the array:

```php
$this->cacheItem = $this->cache->getItem($this->getCacheKey());

if (! $this->cacheItem->isHit()) {
    $this->icons = $this->buildIconsList();
    $this->saveRegistryCache();
}
```

Cache keys are `'symfony_design_system_icons_list_' . $this->getLibraryKey()` — the `symfony_design_system` prefix is inherited from an earlier package and does not match this one's name. Rendered SVG is cached too, one entry per set of CSS classes keyed by `md5(implode($classes))`, written back into the same cache item by `loadIconSvg()`. That method parses the file with `DOMDocument`, appends `icon` plus the caller's classes to the root `<svg>` element's `class` attribute, and re-serialises. This is what `ext-dom` in composer.json is for.

`iconList()` is the exception to the caching: it calls `buildIconsList()` directly and rescans the filesystem each time.

The three concrete managers differ in where their icons come from:

- src/Service/Icon/FaIconManager.php scans `vendor/fortawesome/font-awesome/svgs/` under the project dir, two levels deep, keying icons as `group/name` (`solid/coffee`). Its `iconTag()` nests `<i class="fa fa-solid fa-coffee">` inside the requested tag, turning the `/` into a second class.
- src/Service/Icon/MaterialIconManager.php scans `node_modules/material-design-icons/*/svg/production/`, keeping only files matching `ic_*_24px.svg` and stripping both affixes. Its `iconTag()` emits the name as tag text with the `material-icons` class.
- src/Service/Icon/PhosphorIconManager.php has no index at all: `buildIconsList()` returns `[]` and `hasIcon()` is overridden to `$name !== ''`. Names are `style/icon` and become the classes `ph-bold ph-heart`. Nothing is validated, and nothing is read from disk.

Both filesystem scans are guarded by `is_dir()` and silently produce an empty index when the library is not installed — a missing dependency shows up as icons rendering as text, not as an error.

### Front-end assets

assets carries no PHP. assets/css/partials/_font-all.scss is the single entry point and only forwards the per-library partials:

```scss
@forward 'font-fa';
@forward 'font-phosphor';
```

Each partial imports from `node_modules`, and the libraries themselves are declared as `peerDependencies` in assets/package.json — the host application installs them. There is no Material partial: that library is served by the `material-icons` font class alone.

### TemplateHelper

src/Helper/TemplateHelper.php is a static-only class about view paths and names, unrelated to icons and to the container. It holds `TEMPLATE_FILE_EXTENSION = '.html.twig'` and `VIEW_PATH_PREFIX = '@'`, and offers `removeExtension()`, `trimPathPrefix()`, `joinNormalizedParts()` (snake-cases each part before joining), `explodeControllerNamespaceSubParts()` (drops the bundle-length prefix from a controller namespace, or two segments when no bundle is given) and `stripTwigContextKeys()`. Add a pure string function here; add anything needing the kernel, the cache or Twig under `src/Service/`, where the container will autowire it.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- ext-dom: *
- league/commonmark: ^2.4
- wexample/symfony-helpers: >=6.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
