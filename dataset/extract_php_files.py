import os
import shutil
import glob
from pathlib import Path

def extract_php_files(source_dir, destination_dir):
    """Extract PHP files preserving directory structure."""
    Path(destination_dir).mkdir(parents=True, exist_ok=True)
    
    php_files = []
    for root, dirs, files in os.walk(source_dir):
        for file in files:
            if file.endswith('.php'):
                php_files.append(os.path.join(root, file))
    
    print(f"Found {len(php_files)} PHP files in {source_dir}")
    
    copied_count = 0
    for php_file in php_files:
        # Get relative path from source directory
        rel_path = os.path.relpath(php_file, source_dir)
        
        # Create destination path
        dest_path = os.path.join(destination_dir, rel_path)
        
        dest_dir = os.path.dirname(dest_path)
        Path(dest_dir).mkdir(parents=True, exist_ok=True)
        
        try:
            # Copy the file
            shutil.copy2(php_file, dest_path)
            copied_count += 1
            print(f"Copied: {rel_path}")
        except Exception as e:
            print(f"Error copying {rel_path}: {e}")
    
    print(f"\nSuccessfully copied {copied_count} PHP files to {destination_dir}")
    return copied_count

def main():
    # Define source and destination directories
    source_directory = "wordpress_4.0"
    destination_directory = "extracted_php_files"
    
    # Check if source directory exists
    if not os.path.exists(source_directory):
        print(f"Error: Source directory '{source_directory}' does not exist!")
        return
    
    print(f"Extracting PHP files from '{source_directory}' to '{destination_directory}'...")
    
    # Extract PHP files
    copied_files = extract_php_files(source_directory, destination_directory)
    
    if copied_files > 0:
        print(f"\n✅ Extraction completed successfully!")
        print(f"📁 Destination folder: {destination_directory}")
        print(f"📄 Total PHP files copied: {copied_files}")
    else:
        print("\n❌ No PHP files were copied. Please check the source directory.")

if __name__ == "__main__":
    main()
