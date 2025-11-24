## Documentation Summary Generator

## What ⚡
Generates useful CSV or markdown summaries of large, complicated documentation repositories: 
- All internal and external links
- All pages in one file sorted by folder or alphabetically

| Dir | to | Dir Sum |
|-----------|-----------|-----------|
| ![Directory](https://drive.google.com/thumbnail?id=1Q8sp315z3QHwq95FGAMiTIbrxanWc21G&sz=s100) | ![Convert](https://drive.google.com/thumbnail?id=1rCdqG_aHwvMUZDZU1RKK9WNH_ck0oR3P&sz=s100) | ![Summary](https://drive.google.com/thumbnail?id=180kLHm_hsT5JSQVtGma4Gd9_Hio8-3lV&sz=s100) |

## Why 🤷‍♂️
- Database-drive content managements like Notion are nice because you can also view database summaries of your writing
- Documentation written in plain text and markdown doesn't easily allow for this
- This simple one-file script aims give you some of the content summary powers of tools like Notion within your flat-file system and for you documentation! 

## Process 📋

Overview video:

[![Watch the video](https://drive.google.com/thumbnail?id=1Bdb5pYwVmrz-r6TL6X0nDy-9wQPAfZmj&sz=s225)](https://youtu.be/Ygfg6Dzn5ao)

2nd video on how this can work with Obsidian: 

[![Watch the video](https://drive.google.com/thumbnail?id=114LTAOPvkNCKOx3DaYsDeWdkS0a5EHmV&sz=s225)](https://youtu.be/bEFrNui-IE0)

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

## Ideas 💡
- Instead of "DirectoryToCSV" call this "MDReports" or "DirReports" or "MDStats"or "FlatStats"
- AUTHOR REPORTS -- by RK, by Tim, by Jane ... if Authors name stored in frontmatter could do this
- Word count - added a Wordcount feature but this is best with just a report on PAGES instead of LINKS
- Backlinks - Could calculate this ... find ones without any
- TAG REPORTS and sorted - Also from frontamtterYou
- JSON Export as well ... 
