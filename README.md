## REAMDE


## Process ..

This script will:

1. Define a root directory to scan (you'll need to modify `$rootDirectory` to match your needs)
2. Create a CSV file with appropriate headers
3. Recursively scan for all markdown files (`.md` and `.markdown` extensions)
4. For each markdown file:
   - Extract both markdown-style links `[text](url)` and plain URLs
   - Get the file's creation and modification dates
   - Write each found link to the CSV file with all required information

The resulting CSV will have these columns:
- URL: The extracted URL
- Link Name: The text of the markdown link (if present)
- Source File: Full path to the file where the link was found
- Creation Date: Creation date of the source file
- Last Modified Date: Last modification date of the source file

To use this script:
1. Save it as `Run.php`
2. Modify the `$rootDirectory` path to point to your markdown files directory
3. Run it from the command line using `php Run.php`

The script will create a CSV file named `extracted_links.csv` in the same directory as the script.

Note: File creation time (`filectime`) might not be reliable on all systems, as it sometimes returns the last inode change time instead of the actual file creation time. This is a limitation of the underlying filesystem and PHP's access to this information.
