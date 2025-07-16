<?php

// Load configuration from config.ini file
$configFile = __DIR__ . '/config.ini';
if (!file_exists($configFile)) {
    die("Configuration file 'config.ini' not found. Please create it with the required settings.\n");
}

$config = parse_ini_file($configFile, true);
if ($config === false) {
    die("Error reading configuration file 'config.ini'. Please check the file format.\n");
}

// Configuration parameters
$rootDirectory = $config['root_directory'];  // Example path, modify as needed
$orderBy = $config['order_by']; // Can be "default", "creation_newest", "modified_newest", or "domain"
$reportType = $config['report_type']; // Can be "links_external", "links_internal", "pages_alpha", or "pages_byfolder"

// Export path configuration
$exportPath = $config['export_path'] ?? 'exports';

// Output file paths - always generated based on report_type
// Use absolute path if exportPath starts with /, otherwise relative to script directory
$basePath = (substr($exportPath, 0, 1) === '/') ? $exportPath : __DIR__ . "/{$exportPath}";
$output_csv = "{$basePath}/{$reportType}/{$reportType}.csv";
$output_md = "{$basePath}/{$reportType}/{$reportType}.md";

// Create directories if they don't exist
if (!file_exists($rootDirectory)) {
    mkdir($rootDirectory, 0755, true);
    echo "Created directory: $rootDirectory\n";
    echo "Please add your markdown files to this directory and run the script again.\n";
    exit;
}

// Create export directories if they don't exist
$exportDir = "{$basePath}/{$reportType}";
if (!file_exists($exportDir)) {
    mkdir($exportDir, 0755, true);
    echo "Created export directory: $exportDir\n";
}

// Function to recursively get all markdown files
function findMarkdownFiles($dir) {
    if (!is_dir($dir)) {
        throw new Exception("Directory '$dir' does not exist or is not accessible.");
    }
    
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['md', 'markdown'])) {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

// Function to extract links from markdown content
function extractLinks($content, $reportType) {
    $links = [];
    
    // Match markdown links [text](url)
    preg_match_all('/\[([^\]]*)\]\(([^\)]+)\)/', $content, $markdownLinks, PREG_SET_ORDER);
    foreach ($markdownLinks as $match) {
        $url = trim($match[2]);
        $name = trim($match[1]);
        
        // Check if the link matches the requested type
        $isExternal = preg_match('/^https?:\/\//', $url);
        
        if (($reportType === "links_external" && $isExternal) || 
            ($reportType === "links_internal" && !$isExternal)) {
            $links[] = [
                'url' => $url,
                'name' => ($name !== $url) ? $name : ''
            ];
        }
    }
    
    // Match plain URLs (only for external links)
    if ($reportType === "links_external") {
        preg_match_all('/(?<![\(\[])(https?:\/\/[^\s\)]+)/', $content, $plainUrls);
        foreach ($plainUrls[1] as $url) {
            $links[] = [
                'url' => trim($url),
                'name' => ''
            ];
        }
    }
    
    return $links;
}

// Function to get real creation time on macOS
function getMacCreationTime($file) {
    if (PHP_OS === 'Darwin') { // Check if running on macOS
        $stat = shell_exec('stat -f %B ' . escapeshellarg($file));
        return trim($stat) ? date('Y-m-d H:i:s', trim($stat)) : date('Y-m-d H:i:s', filectime($file));
    }
    return date('Y-m-d H:i:s', filectime($file));
}

// Function to extract domain from URL
function extractDomain($url) {
    // Remove protocol (http, https, etc.)
    $domain = preg_replace('(^https?://)', '', $url);
    // Remove path, query string, and fragment
    $domain = strtok($domain, '/');
    // Remove www. if present
    $domain = preg_replace('/^www\./', '', $domain);
    return $domain;
}

// Function to get relative path
function getRelativePath($fullPath, $rootDirectory) {
    // Ensure paths use consistent directory separators and no trailing slash
    $fullPath = rtrim(str_replace('\\', '/', $fullPath), '/');
    $rootDirectory = rtrim(str_replace('\\', '/', $rootDirectory), '/');
    
    // Remove root directory path
    if (strpos($fullPath, $rootDirectory) === 0) {
        $relativePath = substr($fullPath, strlen($rootDirectory));
    } else {
        $relativePath = $fullPath;
    }
    
    // Remove leading slash if present
    return ltrim($relativePath, '/');
}

// Function to count words in markdown content
function countWords($content) {
    // Remove markdown links [text](url) and replace with just the text
    $content = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $content);
    
    // Remove URLs
    $content = preg_replace('/https?:\/\/\S+/', '', $content);
    
    // Remove markdown headers (#)
    $content = preg_replace('/^#+\s.*$/m', '', $content);
    
    // Remove special characters and extra whitespace
    $content = preg_replace('/[^\w\s]/', ' ', $content);
    $content = preg_replace('/\s+/', ' ', $content);
    
    // Count words
    return str_word_count(trim($content));
}

// Main process
$markdownFiles = findMarkdownFiles($rootDirectory);
$allLinks = []; // Array to store all links before writing

foreach ($markdownFiles as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    $relativePath = getRelativePath($file, $rootDirectory);
    $creationTime = getMacCreationTime($file);
    $modifiedTime = date('Y-m-d H:i:s', filemtime($file));
    $wordCount = countWords($content);
    
    if ($reportType === "pages_alpha" || $reportType === "pages_byfolder") {
        // For pages reports, store one entry per file
        $allLinks[] = [
            'filename' => $filename,
            'fullpath' => $relativePath,
            'creation_time' => $creationTime,
            'modified_time' => $modifiedTime,
            'word_count' => $wordCount
        ];
    } else {
        // For link reports, process links as before
        $links = extractLinks($content, $reportType);
        foreach ($links as $link) {
            $allLinks[] = [
                'domain' => $reportType === "links_external" ? extractDomain($link['url']) : "",
                'filename' => $filename,
                'url' => $link['url'],
                'name' => $link['name'],
                'fullpath' => $relativePath,
                'creation_time' => $creationTime,
                'modified_time' => $modifiedTime,
                'word_count' => $wordCount
            ];
        }
    }
}

// Sort links based on orderBy setting
if ($orderBy === "creation_newest") {
    usort($allLinks, function($a, $b) {
        // Reverse the comparison to get newest first
        return strtotime($b['creation_time']) - strtotime($a['creation_time']);
    });
} elseif ($orderBy === "modified_newest") {
    usort($allLinks, function($a, $b) {
        // Sort by modified time, newest first
        return strtotime($b['modified_time']) - strtotime($a['modified_time']);
    });
} elseif ($orderBy === "domain") {
    usort($allLinks, function($a, $b) {
        // Sort by domain alphabetically
        // If domains are equal, sort by URL
        $domainCompare = strcasecmp($a['domain'], $b['domain']);
        return $domainCompare === 0 ? 
            strcasecmp($a['url'], $b['url']) : 
            $domainCompare;
    });
}

// Write CSV file
    if ($reportType === "pages_byfolder" || $reportType === "pages_alpha") {
        // Initialize CSV file with headers for pages report
        $csvHeaders = ['file', 'folder1', 'folder2', 'folder3', 'folder4', 'source_file', 'creation_date', 'modified_date', 'word_count'];
        $fp = fopen($output_csv, 'w');
        fputcsv($fp, $csvHeaders, ',', '"', '\\');

        // Sort files
        if ($reportType === "pages_byfolder") {
            usort($allLinks, function($a, $b) {
                // First sort by folder1
                $folder1Compare = strcasecmp(
                    explode('/', trim($a['fullpath'], '/'))[0] ?? '', 
                    explode('/', trim($b['fullpath'], '/'))[0] ?? ''
                );
                if ($folder1Compare !== 0) return $folder1Compare;
                
                // Then by folder2
                $folder2Compare = strcasecmp(
                    explode('/', trim($a['fullpath'], '/'))[1] ?? '', 
                    explode('/', trim($b['fullpath'], '/'))[1] ?? ''
                );
                if ($folder2Compare !== 0) return $folder2Compare;
                
                // Then by folder3
                $folder3Compare = strcasecmp(
                    explode('/', trim($a['fullpath'], '/'))[2] ?? '', 
                    explode('/', trim($b['fullpath'], '/'))[2] ?? ''
                );
                if ($folder3Compare !== 0) return $folder3Compare;
                
                // Finally by filename
                return strcasecmp($a['filename'], $b['filename']);
            });
        } else {
            // Original alphabetical sort for pages_alpha
            usort($allLinks, function($a, $b) {
                return strcasecmp($a['filename'], $b['filename']);
            });
        }

        // Write sorted pages to CSV
        $processedFiles = [];
        foreach ($allLinks as $link) {
            if (!in_array($link['filename'], $processedFiles)) {
                $pathParts = explode('/', trim($link['fullpath'], '/'));
                array_pop($pathParts); // Remove the filename
                $folders = array_pad($pathParts, 4, '');
                
                fputcsv($fp, [
                    $link['filename'],
                    $folders[0],
                    $folders[1],
                    $folders[2],
                    $folders[3],
                    $link['fullpath'],
                    $link['creation_time'],
                    $link['modified_time'],
                    $link['word_count']
                ], ',', '"', '\\');
                $processedFiles[] = $link['filename'];
            }
        }
        fclose($fp);
        echo "CSV extraction completed. Results saved to: $output_csv\n";
    }
    
    if ($reportType === "links_external" || $reportType === "links_internal") {
        // Link report CSV code
        $csvHeaders = ['domain', 'file', 'url', 'link_name', 'source_file', 'creation_date', 'modified_date', 'word_count'];
        $fp = fopen($output_csv, 'w');
        fputcsv($fp, $csvHeaders, ',', '"', '\\');

        foreach ($allLinks as $link) {
            fputcsv($fp, [
                $link['domain'],
                $link['filename'],
                $link['url'],
                $link['name'],
                $link['fullpath'],
                $link['creation_time'],
                $link['modified_time'],
                $link['word_count']
            ], ',', '"', '\\');
        }
        fclose($fp);
        echo "CSV extraction completed. Results saved to: $output_csv\n";
    }

// Write Markdown file
    if ($reportType === "pages_byfolder") {
        $md_content = "# Pages By Folder\n\n";
        
        // Get total count
        $totalPages = count(array_unique(array_column($allLinks, 'filename')));
        $md_content .= "{$totalPages} total pages\n\n";
        
        // Track current folders to know when to add headers
        $currentFolder1 = '';
        $currentFolder2 = '';
        $currentFolder3 = '';
        
        foreach ($allLinks as $link) {
            $pathParts = explode('/', trim($link['fullpath'], '/'));
            $filename = array_pop($pathParts); // Remove and store filename
            $folders = array_pad($pathParts, 4, '');
            
            // Add folder1 header if changed
            if ($folders[0] !== '' && $folders[0] !== $currentFolder1) {
                $md_content .= "\n## {$folders[0]}\n";
                $currentFolder1 = $folders[0];
                $currentFolder2 = ''; // Reset subfolder tracking
                $currentFolder3 = '';
            }
            
            // Add folder2 header if changed
            if ($folders[1] !== '' && $folders[1] !== $currentFolder2) {
                $md_content .= "\n### {$folders[1]}\n";
                $currentFolder2 = $folders[1];
                $currentFolder3 = ''; // Reset sub-subfolder tracking
            }
            
            // Add folder3 header if changed
            if ($folders[2] !== '' && $folders[2] !== $currentFolder3) {
                $md_content .= "\n#### {$folders[2]}\n";
                $currentFolder3 = $folders[2];
            }
            
            // Remove .md extension for display
            $displayName = preg_replace('/\.md$/', '', $filename);
            $encodedPath = str_replace(' ', '%20', $link['fullpath']);
            $encodedPath = str_replace('⭐', '%E2%AD%90', $encodedPath);
            
            $md_content .= "- [{$displayName}]({$encodedPath})\n";
        }
    } elseif ($reportType === "pages_alpha") {
        $md_content = "# Pages\n\n";
        
        // Get unique files and sort alphabetically
        $uniqueFiles = array_unique(array_column($allLinks, 'filename'));
        sort($uniqueFiles, SORT_STRING | SORT_FLAG_CASE);
        
        // Add total count
        $totalPages = count($uniqueFiles);
        $md_content .= "{$totalPages} total pages\n\n";
        
        // Group by first letter
        $currentLetter = '';
        foreach ($uniqueFiles as $filename) {
            $firstLetter = strtoupper(substr($filename, 0, 1));
            
            if ($firstLetter !== $currentLetter) {
                $md_content .= "\n## {$firstLetter}\n";
                $currentLetter = $firstLetter;
            }
            
            // Remove .md extension for display
            $displayName = preg_replace('/\.md$/', '', $filename);
            $encodedPath = str_replace(' ', '%20', array_search($filename, array_column($allLinks, 'filename', 'fullpath')));
            $encodedPath = str_replace('⭐', '%E2%AD%90', $encodedPath);
            
            $md_content .= "- [{$displayName}]({$encodedPath})\n";
        }
    } else {
        // Existing link report markdown code
        $title = $reportType === "links_external" ? "External Links" : "Internal Links";
        $md_content = "# {$title}\n\n";
        
        // Count unique domains and total URLs
        $totalUrls = count($allLinks);
        $uniqueDomains = 0;
        $currentDomain = '';
        
        // Count unique domains based on headers
        foreach ($allLinks as $link) {
            $header = '';
            if ($orderBy === 'domain' && !empty($link['domain'])) {
                $header = $link['domain'];
            } elseif ($orderBy === 'creation_newest') {
                $header = date('Y-m-d', strtotime($link['creation_time']));
            } elseif ($orderBy === 'modified_newest') {
                $header = date('Y-m-d', strtotime($link['modified_time']));
            }
            
            if ($header !== $currentDomain) {
                $uniqueDomains++;
                $currentDomain = $header;
            }
        }
        
        // Add summary line
        if ($orderBy === 'domain') {
            $md_content .= "{$totalUrls} URLs from {$uniqueDomains} domains\n\n";
        }
        
        $current_header = '';
        foreach ($allLinks as $link) {
            // Determine header based on orderBy
            $header = '';
            if ($orderBy === 'domain' && !empty($link['domain'])) {
                $header = $link['domain'];
            } elseif ($orderBy === 'creation_newest') {
                $header = date('Y-m-d', strtotime($link['creation_time']));
            } elseif ($orderBy === 'modified_newest') {
                $header = date('Y-m-d', strtotime($link['modified_time']));
            }
            
            // Add header if it's different from current
            if ($header && $header !== $current_header) {
                $md_content .= ($current_header ? "\n" : "") . "## {$header}\n";
                $current_header = $header;
            }
            
            // URL encode the relative path for the markdown link
            $encodedPath = str_replace(' ', '%20', $link['fullpath']);
            $encodedPath = str_replace('⭐', '%E2%AD%90', $encodedPath);
            
            // Add link to URL and encoded source file with dash bullet
            $md_content .= "- [{$link['url']}]({$link['url']}) - []({$encodedPath})\n";
        }
    }
    
    file_put_contents($output_md, $md_content);
    echo "Markdown extraction completed. Results saved to: $output_md\n";