## REAMDE


## Process ..


### Script Overview

This script will:
1. Define configurable parameters:
   - `$rootDirectory`: Directory to scan for markdown files
   - `$orderBy`: Sort order ("default", "creation_newest", "modified_newest", or "domain")
   - `$linkType`: Type of links to extract ("internal" or "external")

2. Create a CSV file with appropriate headers

3. Recursively scan for all markdown files (`.md` and `.markdown` extensions)

4. For each markdown file:
   - Extract links based on `$linkType`:
     * External: Both markdown-style links and plain URLs starting with http(s)
     * Internal: Only markdown-style links to local files
   - Extract root domains from URLs (for external links)
   - Get accurate file creation dates (specifically for macOS)
   - Get file modification dates
   - Store all information for sorting

5. Sort the collected data based on `$orderBy`:
   - "default": Original scan order
   - "creation_newest": Newest files first
   - "modified_newest": Most recently modified first
   - "domain": Alphabetically by root domain

### CSV Output Columns
1. Domain: Root domain of the URL (for external links)
2. File: Just the filename
3. URL: The complete extracted URL
4. Link Name: The text of the markdown link (if different from URL)
5. Source File: Full path to the file where the link was found
6. Creation Date: Accurate creation date of the source file
7. Last Modified Date: Last modification date of the source file

### Usage
1. Save as `Run.php`
2. Configure parameters:
   ```php
   $rootDirectory = __DIR__ . '/docs';
   $orderBy = "default";  // or "creation_newest", "modified_newest", "domain"
   $linkType = "external";  // or "internal"
   ```
3. Run: `php Run.php`

### Notes
- Creates `/docs` directory if it doesn't exist
- Uses `stat` command on macOS for accurate creation dates
- Groups links by domain when using domain sorting
- Filters internal/external links based on `$linkType`
- Outputs to `extracted_links.csv` in the same directory as the script
