<?php

// Configuration parameters
$rootDirectory = '/Users/Reess/Desktop/ReessDB/repos/Wordpress/';  // Example path, modify as needed
$orderBy = "default"; // Can be "default", "creation_newest", "modified_newest", or "domain"
$reportType = "pages_alpha"; // Can be "links_external", "links_internal", or "pages_alpha"

// Output file paths (comment out to skip generation)
$output_csv = __DIR__ . "/exports/{$reportType}/{$reportType}.csv";
$output_md = __DIR__ . "/exports/{$reportType}/{$reportType}.md";

// Create directories if they don't exist
if (!file_exists($rootDirectory)) {
    mkdir($rootDirectory, 0755, true);
    echo "Created directory: $rootDirectory\n";
    echo "Please add your markdown files to this directory and run the script again.\n";
    exit;
}

// Create export directories if they don't exist
if (isset($output_csv) || isset($output_md)) {
    $exportDir = __DIR__ . "/exports/{$reportType}";
    if (!file_exists($exportDir)) {
        mkdir($exportDir, 0755, true);
        echo "Created export directory: $exportDir\n";
    }
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
    // Ensure paths use consistent directory separators
    $fullPath = str_replace('\\', '/', $fullPath);
    $rootDirectory = str_replace('\\', '/', $rootDirectory);
    
    // Remove root directory path and leading slash
    $relativePath = str_replace($rootDirectory . '/', '', $fullPath);
    
    return $relativePath;
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
    
    if ($reportType === "pages_alpha") {
        // For pages report, store one entry per file
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

// Write CSV if output path is defined
if (isset($output_csv)) {
    if ($reportType === "pages_alpha") {
        // Initialize CSV file with headers for pages report
        $csvHeaders = ['file', 'source_file', 'creation_date', 'modified_date', 'word_count'];
        $fp = fopen($output_csv, 'w');
        fputcsv($fp, $csvHeaders);

        // Sort files alphabetically
        usort($allLinks, function($a, $b) {
            return strcasecmp($a['filename'], $b['filename']);
        });

        // Write sorted pages to CSV (one entry per file)
        $processedFiles = [];
        foreach ($allLinks as $link) {
            if (!in_array($link['filename'], $processedFiles)) {
                fputcsv($fp, [
                    $link['filename'],
                    $link['fullpath'],
                    $link['creation_time'],
                    $link['modified_time'],
                    $link['word_count']
                ]);
                $processedFiles[] = $link['filename'];
            }
        }
    } else {
        // Existing link report CSV code
        $csvHeaders = ['domain', 'file', 'url', 'link_name', 'source_file', 'creation_date', 'modified_date', 'word_count'];
        $fp = fopen($output_csv, 'w');
        fputcsv($fp, $csvHeaders);

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
            ]);
        }
    }
    fclose($fp);
    echo "CSV extraction completed. Results saved to: $output_csv\n";
}

// Write Markdown if output path is defined
if (isset($output_md)) {
    if ($reportType === "pages_alpha") {
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
}