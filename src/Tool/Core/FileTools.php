<?php

declare(strict_types=1);

namespace Elabx\ProcessWireMcp\Tool\Core;

use Elabx\ProcessWireMcp\Tool\ProcessWireMcpTool;
use Mcp\Capability\Attribute\McpTool;
use ProcessWire\NullPage;

/**
 * File management tools for ProcessWire MCP
 *
 * Provides tools for listing and inspecting files attached to pages.
 * Only ProcessWire-managed files are accessible.
 */
class FileTools extends ProcessWireMcpTool
{
    public static function getToolInfo(): array
    {
        return [
            'name' => 'file_tools',
            'description' => 'File inspection tools for ProcessWire pages',
            'priority' => 50,
        ];
    }

    /**
     * Get files/images attached to a page
     *
     * @param int|string $page Page ID or path
     * @param string|null $field Specific file/image field name (null = all)
     * @return array Files data
     */
    #[McpTool(
        name: 'get_page_files',
        description: 'Get files and images attached to a page. Optionally specify a field name to filter.'
    )]
    public function getPageFiles(int|string $page, ?string $field = null): array
    {
        try {
            $pageObj = $this->pages()->get($page);

            if (!$pageObj || $pageObj instanceof NullPage || !$pageObj->id) {
                return $this->error("Page not found: {$page}", 'NOT_FOUND');
            }

            // Security: Block admin pages
            $this->assertNotAdminPage($pageObj);

            $allFiles = [];

            // Get file/image fields from template
            foreach ($pageObj->template->fieldgroup as $fieldObj) {
                $fieldType = $fieldObj->type->className();

                // Only process file and image fields
                if (!in_array($fieldType, ['FieldtypeFile', 'FieldtypeImage'])) {
                    continue;
                }

                // Filter by specific field if requested
                if ($field !== null && $fieldObj->name !== $field) {
                    continue;
                }

                $files = $pageObj->get($fieldObj->name);
                if (!$files || !$files->count()) {
                    continue;
                }

                $fieldFiles = [];
                foreach ($files as $file) {
                    $fileData = [
                        'name' => $file->name,
                        'basename' => $file->basename,
                        'url' => $file->url,
                        'httpUrl' => $file->httpUrl,
                        'filename' => $file->filename,
                        'ext' => $file->ext,
                        'filesize' => $file->filesize,
                        'filesizeStr' => $file->filesizeStr,
                        'description' => $file->description,
                        'tags' => $file->tags,
                        'created' => $file->created,
                        'modified' => $file->modified,
                    ];

                    // Add image-specific data
                    if ($file instanceof \ProcessWire\Pageimage) {
                        $fileData['width'] = $file->width;
                        $fileData['height'] = $file->height;
                        $fileData['is_image'] = true;

                        // Include variation info if available
                        if (method_exists($file, 'getVariations')) {
                            $variations = $file->getVariations();
                            $fileData['variation_count'] = $variations->count();
                        }
                    } else {
                        $fileData['is_image'] = false;
                    }

                    $fieldFiles[] = $fileData;
                }

                $allFiles[] = [
                    'field' => $fieldObj->name,
                    'field_type' => $fieldType,
                    'count' => count($fieldFiles),
                    'files' => $fieldFiles,
                ];
            }

            return $this->success([
                'page' => [
                    'id' => $pageObj->id,
                    'title' => $pageObj->title,
                    'path' => $pageObj->path,
                ],
                'field_count' => count($allFiles),
                'total_files' => array_sum(array_column($allFiles, 'count')),
                'fields' => $allFiles,
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to get page files: ' . $e->getMessage());
        }
    }

    /**
     * Get image variations for a page image
     *
     * @param int|string $page Page ID or path
     * @param string $field Image field name
     * @param string $filename Image filename
     * @return array Image variation data
     */
    #[McpTool(
        name: 'get_image_variations',
        description: 'Get all size variations of an image attached to a page.'
    )]
    public function getImageVariations(int|string $page, string $field, string $filename): array
    {
        try {
            $pageObj = $this->pages()->get($page);

            if (!$pageObj || $pageObj instanceof NullPage || !$pageObj->id) {
                return $this->error("Page not found: {$page}", 'NOT_FOUND');
            }

            // Security: Block admin pages
            $this->assertNotAdminPage($pageObj);

            // Check field exists and is an image field
            $fieldObj = $pageObj->template->fieldgroup->getField($field);
            if (!$fieldObj) {
                return $this->error("Field not found: {$field}", 'NOT_FOUND');
            }

            if ($fieldObj->type->className() !== 'FieldtypeImage') {
                return $this->error("Field '{$field}' is not an image field", 'INVALID_INPUT');
            }

            $images = $pageObj->get($field);
            $image = $images->get("name={$filename}");

            if (!$image) {
                return $this->error("Image not found: {$filename}", 'NOT_FOUND');
            }

            // Get original image info
            $original = [
                'name' => $image->name,
                'url' => $image->url,
                'width' => $image->width,
                'height' => $image->height,
                'filesize' => $image->filesize,
            ];

            // Get variations
            $variations = [];
            if (method_exists($image, 'getVariations')) {
                foreach ($image->getVariations() as $variation) {
                    $variations[] = [
                        'name' => $variation->name,
                        'url' => $variation->url,
                        'width' => $variation->width,
                        'height' => $variation->height,
                        'filesize' => $variation->filesize,
                    ];
                }
            }

            return $this->success([
                'original' => $original,
                'variation_count' => count($variations),
                'variations' => $variations,
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to get image variations: ' . $e->getMessage());
        }
    }

    /**
     * Get files directory path for a page
     *
     * @param int|string $page Page ID or path
     * @return array Directory information
     */
    #[McpTool(
        name: 'get_page_files_path',
        description: 'Get the files directory path for a page.'
    )]
    public function getPageFilesPath(int|string $page): array
    {
        try {
            $pageObj = $this->pages()->get($page);

            if (!$pageObj || $pageObj instanceof NullPage || !$pageObj->id) {
                return $this->error("Page not found: {$page}", 'NOT_FOUND');
            }

            // Security: Block admin pages
            $this->assertNotAdminPage($pageObj);

            $config = $this->wire->wire('config');

            // Page files are stored in site/assets/files/{page_id}/
            $filesPath = $config->paths->files . $pageObj->id . '/';
            $filesUrl = $config->urls->files . $pageObj->id . '/';

            $exists = is_dir($filesPath);

            $result = [
                'page' => [
                    'id' => $pageObj->id,
                    'title' => $pageObj->title,
                ],
                'path' => $filesPath,
                'url' => $filesUrl,
                'exists' => $exists,
            ];

            if ($exists) {
                // Count files in directory
                $fileCount = count(glob($filesPath . '*'));
                $result['file_count'] = $fileCount;
            }

            return $this->success($result);

        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACCESS_DENIED');
        } catch (\Throwable $e) {
            return $this->error('Failed to get page files path: ' . $e->getMessage());
        }
    }
}
