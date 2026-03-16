<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Data\Data;
use Kirby\Filesystem\Asset;
use Kirby\Image\Image;
use tobimori\SvgOptimizer;

// allow SVGs through the manipulate() pipeline so
// create blueprint options trigger the thumb component
Image::$resizableTypes[] = 'svg';

App::plugin('tobimori/svgo', [
	'components' => [
		'file::version' => function (App $kirby, File|Asset $file, array $options = []): File|Asset|FileVersion {
			if (
				pathinfo($file->filename(), PATHINFO_EXTENSION) !== 'svg' ||
				isset($options['svg']) === false
			) {
				return ($kirby->nativeComponent('file::version'))($kirby, $file, $options);
			}

			$mediaRoot = $file->mediaDir();
			$baseName = pathinfo($file->filename(), PATHINFO_FILENAME);
			$rulesHash = hash('xxh3', serialize($options['svg']));
			$thumbName = $baseName . '-' . $rulesHash . '.svg';
			$thumbRoot = $mediaRoot . '/' . $thumbName;
			$job = $mediaRoot . '/.jobs/' . $thumbName . '.json';

			if (file_exists($thumbRoot) === false && file_exists($job) === false) {
				try {
					Data::write($job, [...$options, 'filename' => $file->filename()]);
				} catch (Throwable) {
					return $file;
				}
			}

			return new FileVersion([
				'modifications' => $options,
				'original' => $file,
				'root' => $thumbRoot,
				'url' => $file->mediaUrl($thumbName),
			]);
		},
		'thumb' => function (App $kirby, string $src, string $dst, array $options): string {
			if (pathinfo($src, PATHINFO_EXTENSION) !== 'svg') {
				return $kirby->nativeComponent('thumb')($kirby, $src, $dst, $options);
			}

			return SvgOptimizer::process($src, $dst, $options);
		}
	],
	'fileMethods' => [
		/** @kql-allowed */
		'svgo' => fn (array $options = []) => SvgOptimizer::thumb($this, $options),
	],
	'options' => [
		'rules' => [
			'convertColorsToHex' => true,
			'convertCssClassesToAttributes' => true,
			'convertEmptyTagsToSelfClosing' => true,
			'convertInlineStylesToAttributes' => true,
			'fixAttributeNames' => false,
			'flattenGroups' => true,
			'minifySvgCoordinates' => true,
			'minifyTransformations' => true,
			'removeAriaAndRole' => true,
			'removeComments' => true,
			'removeDataAttributes' => false,
			'removeDefaultAttributes' => true,
			'removeDeprecatedAttributes' => true,
			'removeDoctype' => true,
			'removeDuplicateElements' => true,
			'removeEmptyAttributes' => true,
			'removeEmptyGroups' => true,
			'removeEmptyTextAttributes' => true,
			'removeEnableBackgroundAttribute' => false,
			'removeInkscapeFootprints' => true,
			'removeInvisibleCharacters' => true,
			'removeMetadata' => true,
			'removeNonStandardAttributes' => false,
			'removeNonStandardTags' => false,
			'removeTitleAndDesc' => true,
			'removeUnnecessaryWhitespace' => true,
			'removeUnsafeElements' => false,
			'removeUnusedMasks' => true,
			'removeUnusedNamespaces' => true,
			'removeWidthHeightAttributes' => false,
			'scopeSvgStyles' => false,
			'sortAttributes' => true,
		],
	],
]);
