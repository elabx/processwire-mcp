<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use ProcessWire\NullPage;

/**
 * Image management tools for ProcessWire MCP
 *
 * Provides tools for copying images between pages.
 */
class ImageTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'image_tools',
            'description' => 'Image management tools for ProcessWire',
            'priority' => 55,
        ];
    }

    /**
     * Copy image(s) from one page field to another
     *
     * @param int|string $sourcePage Source page ID or path
     * @param string $sourceField Source image field name
     * @param int|string $targetPage Target page ID or path
     * @param string $targetField Target image field name
     * @param string|null $filename Optional specific filename to copy (copies all if omitted)
     * @return array Result with copied image info
     */
    #[McpTool(
        name: 'copy_page_image',
        description: 'Copy image(s) from one page\'s image field to another page\'s image field. Optionally specify a filename to copy a single image; otherwise copies all.'
    )]
    public function copyPageImage(
        int|string $sourcePage,
        string $sourceField,
        int|string $targetPage,
        string $targetField,
        ?string $filename = null
    ): array {
        try {
            // Resolve source page
            $sourcePageObj = $this->pages()->get($sourcePage);
            if (!$sourcePageObj || $sourcePageObj instanceof NullPage || !$sourcePageObj->id) {
                return $this->error("Source page not found: {$sourcePage}", 'NOT_FOUND');
            }

            // Resolve target page
            $targetPageObj = $this->pages()->get($targetPage);
            if (!$targetPageObj || $targetPageObj instanceof NullPage || !$targetPageObj->id) {
                return $this->error("Target page not found: {$targetPage}", 'NOT_FOUND');
            }

            // Security: check both pages (target might be a repeater item)
            $this->assertContentPageNotAdmin($sourcePageObj);
            $this->assertContentPageNotAdmin($targetPageObj);

            // Validate source field
            $sourceFieldObj = $this->fields()->get($sourceField);
            if (!$sourceFieldObj) {
                return $this->error("Source field not found: {$sourceField}", 'NOT_FOUND');
            }
            if (!in_array($sourceFieldObj->type->className(), ['FieldtypeImage', 'FieldtypeFile'])) {
                return $this->error(
                    "Source field '{$sourceField}' is {$sourceFieldObj->type->className()}, not an image/file field.",
                    'INVALID_INPUT'
                );
            }

            // Validate target field
            $targetFieldObj = $this->fields()->get($targetField);
            if (!$targetFieldObj) {
                return $this->error("Target field not found: {$targetField}", 'NOT_FOUND');
            }
            if (!in_array($targetFieldObj->type->className(), ['FieldtypeImage', 'FieldtypeFile'])) {
                return $this->error(
                    "Target field '{$targetField}' is {$targetFieldObj->type->className()}, not an image/file field.",
                    'INVALID_INPUT'
                );
            }

            $sourceImages = $sourcePageObj->getUnformatted($sourceField);
            $targetImages = $targetPageObj->getUnformatted($targetField);
            $copied = [];

            if ($filename) {
                // Copy a single image by filename
                $image = $sourceImages->get("name={$filename}");
                if (!$image) {
                    return $this->error(
                        "Image '{$filename}' not found in {$sourceField} on page {$sourcePageObj->id}.",
                        'NOT_FOUND'
                    );
                }
                $targetImages->add($image->filename);
                $copied[] = $image->name;
            } else {
                // Copy all images
                foreach ($sourceImages as $image) {
                    $targetImages->add($image->filename);
                    $copied[] = $image->name;
                }
            }

            if (empty($copied)) {
                return $this->error('No images found to copy.', 'NOT_FOUND');
            }

            $targetPageObj->save($targetField);

            return $this->success([
                'source_page_id' => $sourcePageObj->id,
                'source_field' => $sourceField,
                'target_page_id' => $targetPageObj->id,
                'target_field' => $targetField,
                'copied' => $copied,
                'count' => count($copied),
            ], count($copied) . ' image(s) copied successfully');

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to copy image: ' . $e->getMessage());
        }
    }
}
