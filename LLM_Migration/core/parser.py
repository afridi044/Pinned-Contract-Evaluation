"""
Output Processing System
Handles parsing of LLM responses and file reconstruction from chunks.
"""

import re
from pathlib import Path
from typing import Dict, List, Any, Optional, Tuple

from core.utils import ensure_directory


class OutputParser:
    """Parses model responses and extracts migrated code."""
    
    def __init__(self):
        self.model_output_path = Path('model_output')
        self.parsed_path = Path('new-version')
        ensure_directory(self.parsed_path)
    
    def extract_migrated_code(self, response_content: str) -> str:
        """Extract code between MIGRATION_START and MIGRATION_END markers."""
        start_match = re.search(r'//\s*MIGRATION_START\s*\n', response_content, re.IGNORECASE)
        end_match = re.search(r'\n//\s*MIGRATION_END', response_content, re.IGNORECASE)
        
        if start_match and end_match:
            # Normal case: both markers present
            return response_content[start_match.end():end_match.start()].strip()
        elif start_match and not end_match:
            # Handle missing end marker (truncated response)
            print("⚠️  Warning: Missing MIGRATION_END marker, extracting until end of response")
            code_content = response_content[start_match.end():].strip()
            # Remove any trailing incomplete lines that might be artifacts
            lines = code_content.split('\n')
            # Remove last line if it looks incomplete (very short or doesn't end properly)
            if lines and len(lines[-1].strip()) < 10 and not lines[-1].strip().endswith((';', '}', '?>')):
                lines = lines[:-1]
            return '\n'.join(lines).strip()
        
        return ""
    
    def extract_metadata(self, response_content: str) -> Dict[str, str]:
        """Extract metadata from response file header."""
        metadata = {}
        for line in response_content.split('\n')[:15]:
            if ':' in line:
                key, value = line.split(':', 1)
                key = key.strip().lower()
                if key in ['file', 'model', 'strategy']:
                    metadata[f'original_{key}' if key == 'file' else key] = value.strip()
        return metadata
    
    def parse_single_file(self, response_file: Path) -> Dict[str, Any]:
        """Parse a single response file."""
        try:
            print(f"Processing {response_file.name}")
            
            with open(response_file, 'r', encoding='utf-8') as f:
                content = f.read()
            
            metadata = self.extract_metadata(content)
            migrated_code = self.extract_migrated_code(content)
            
            if not metadata.get('original_file'):
                print(f"   ERROR: No original file found in metadata")
                return {'success': False}
            
            if not migrated_code:
                print(f"   ERROR: No migrated code found between markers")
                return {'success': False}
            
            print(f"   SUCCESS: Found {len(migrated_code)} chars of migrated code")
            return {
                'success': True,
                'metadata': metadata,
                'migrated_code': migrated_code
            }
        
        except Exception as e:
            print(f"   ERROR: {e}")
            return {'success': False, 'error': str(e)}
    
    def _normalize_model_name(self, model_name: str) -> str:
        """Convert model name to filesystem-safe format."""
        return re.sub(r'[/:.-]', '_', model_name.lower())
    
    def _determine_output_file(self, metadata: Dict[str, str], response_filename: str, model_folder: Path) -> Path:
        """Determine the output file path."""
        original_filename = metadata.get('original_file')
        if original_filename:
            return model_folder / original_filename
        
        # Fallback: derive from response filename
        php_filename = response_filename.replace('.txt', '.php') if response_filename.endswith('.txt') else f"{response_filename}.php"
        return model_folder / php_filename
    
    def save_parsed_file(self, result: Dict[str, Any], response_filename: str, model_folder_name: str = None) -> bool:
        """Save parsed result to organized structure."""
        try:
            metadata = result['metadata']
            migrated_code = result['migrated_code']
            
            # Determine model folder name
            model_clean = model_folder_name or self._normalize_model_name(metadata.get('model', 'unknown_model'))
            
            # Create model folder and output file
            model_folder = self.parsed_path / model_clean
            ensure_directory(model_folder)
            output_file = self._determine_output_file(metadata, response_filename, model_folder)
            
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(migrated_code)
            
            print(f"   ✅ SAVED: {output_file}")
            return True
            
        except Exception as e:
            print(f"   ❌ SAVE ERROR: {e}")
            return False
    
    def _process_response_files(self, response_files: List[Path], model_folder_name: str = None) -> Tuple[int, int]:
        """Process a list of response files and return success/failed counts."""
        success_count = 0
        failed_count = 0
        
        for response_file in response_files:
            result = self.parse_single_file(response_file)
            
            if result['success']:
                # Update metadata with model folder name if provided
                if model_folder_name and 'metadata' in result:
                    result['metadata']['model_folder'] = model_folder_name
                
                if self.save_parsed_file(result, response_file.name, model_folder_name):
                    success_count += 1
                else:
                    failed_count += 1
            else:
                failed_count += 1
        
        return success_count, failed_count
    
    def process_all_responses(self):
        """Process all response files in model_output directory."""
        print("🔄 Processing all model responses...")
        
        if not self.model_output_path.exists():
            print(f"❌ Directory {self.model_output_path} not found")
            return
        
        # Look for model subfolders
        model_folders = [d for d in self.model_output_path.iterdir() if d.is_dir()]
        
        if not model_folders:
            # Fallback: process files directly (old structure)
            response_files = list(self.model_output_path.glob('*.txt'))
            if response_files:
                print(f"📁 Found {len(response_files)} response files in old structure")
                success, failed = self._process_response_files(response_files)
                print(f"\n🎉 Processing completed!")
                print(f"✅ Successfully processed: {success} files")
                print(f"❌ Failed to process: {failed} files")
            else:
                print("❌ No model folders or .txt files found in model_output/")
            return
        
        print(f"📁 Found {len(model_folders)} model folders:")
        for folder in model_folders:
            print(f"   📂 {folder.name}/")
        
        total_success = 0
        total_failed = 0
        
        # Process each model folder
        for model_folder in model_folders:
            print(f"\n🔄 Processing model: {model_folder.name}")
            
            response_files = list(model_folder.glob('*.txt'))
            print(f"   📄 Found {len(response_files)} response files")
            
            if not response_files:
                print("   ⚠️  No .txt files found in this model folder")
                continue
            
            success_count, failed_count = self._process_response_files(response_files, model_folder.name)
            
            print(f"   ✅ Successfully processed: {success_count} files")
            print(f"   ❌ Failed to process: {failed_count} files")
            
            total_success += success_count
            total_failed += failed_count
        
        print(f"\n🎉 Overall processing completed!")
        print(f"✅ Total successfully processed: {total_success} files")
        print(f"❌ Total failed to process: {total_failed} files")
        
        # Show results summary
        if total_success > 0:
            print(f"\n📁 Results saved to '{self.parsed_path}':")
            for model_folder in sorted(self.parsed_path.iterdir()):
                if model_folder.is_dir():
                    php_files = list(model_folder.glob('*.php'))
                    print(f"   📂 {model_folder.name}/ ({len(php_files)} files)")


class FileReconstructor:
    """Reconstructs complete files from parsed chunk files."""
    
    def __init__(self, parser: OutputParser):
        self.parser = parser
        self.chunked_output_path = Path('chunked_model_output')
        self.final_output_path = Path('new-version')
        ensure_directory(self.final_output_path)
    
    def find_chunked_files(self) -> List[Dict[str, Any]]:
        """Find all chunked file directories."""
        if not self.chunked_output_path.exists():
            print("No chunked_model_output directory found")
            return []
        
        chunked_files = []
        
        for model_dir in self.chunked_output_path.iterdir():
            if model_dir.is_dir():
                for file_dir in model_dir.iterdir():
                    if file_dir.is_dir():
                        # Check if it has numbered chunk files
                        chunk_files = list(file_dir.glob('*.txt'))
                        if chunk_files:
                            chunked_files.append({
                                'model': model_dir.name,
                                'filename': file_dir.name,
                                'directory': file_dir,
                                'chunk_count': len(chunk_files)
                            })
        
        return chunked_files
    
    def get_chunk_files(self, directory: Path) -> List[Tuple[int, Path]]:
        """Get all chunk files from a directory, sorted by number."""
        chunk_files = []
        
        for file in directory.glob('*.txt'):
            try:
                # Extract number from filename (1.txt -> 1)
                chunk_num = int(file.stem)
                chunk_files.append((chunk_num, file))
            except ValueError:
                print(f"WARNING: Skipping non-numeric chunk file: {file.name}")
        
        # Sort by chunk number
        chunk_files.sort(key=lambda x: x[0])
        return chunk_files
    
    def reconstruct_file(self, file_info: Dict[str, Any]) -> bool:
        """Reconstruct a complete file from its chunks."""
        print(f"\nReconstructing {file_info['filename']}.php from {file_info['chunk_count']} chunks")
        print(f"Model: {file_info['model']}")
        print(f"Directory: {file_info['directory']}")
        
        # Get sorted chunk files
        chunk_files = self.get_chunk_files(file_info['directory'])
        
        if not chunk_files:
            print("   ERROR: No valid chunk files found")
            return False
        
        # Check for missing chunks
        expected_numbers = list(range(1, len(chunk_files) + 1))
        actual_numbers = [num for num, _ in chunk_files]
        missing = set(expected_numbers) - set(actual_numbers)
        
        if missing:
            print(f"   WARNING: Missing chunks: {sorted(missing)}")
        
        print(f"   Found chunks: {actual_numbers}")
        
        # Parse each chunk
        parsed_chunks = []
        metadata = None
        
        for chunk_num, chunk_file in chunk_files:
            print(f"   Processing chunk {chunk_num}...")
            result = self.parser.parse_single_file(chunk_file)
            
            if result['success']:
                parsed_chunks.append({
                    'number': chunk_num,
                    'code': result['migrated_code'],
                    'metadata': result['metadata']
                })
                
                # Use metadata from first successful chunk
                if metadata is None:
                    metadata = result['metadata']
                    
                print(f"      SUCCESS: {len(result['migrated_code'])} chars")
            else:
                print(f"      ERROR: Failed to parse chunk {chunk_num}")
                parsed_chunks.append({
                    'number': chunk_num,
                    'code': None,
                    'metadata': None
                })
        
        if not any(chunk['code'] for chunk in parsed_chunks):
            print("   ERROR: No chunks could be parsed successfully")
            return False
        
        # Combine chunks
        combined_code = []
        successful_chunks = 0
        
        for chunk in parsed_chunks:
            if chunk['code']:
                combined_code.append(chunk['code'])
                successful_chunks += 1
            else:
                print(f"   WARNING: Chunk {chunk['number']} failed - adding placeholder comment")
                combined_code.append(f"// ERROR: Chunk {chunk['number']} failed to parse")
        
        final_code = ''.join(combined_code)
        print(f"   Combined {successful_chunks}/{len(parsed_chunks)} chunks successfully")
        print(f"   Final code length: {len(final_code)} characters")
        
        # Save reconstructed file
        return self.save_reconstructed_file(file_info, final_code, metadata)
    
    def save_reconstructed_file(self, file_info: Dict[str, Any], code: str, metadata: Optional[Dict[str, str]]) -> bool:
        """Save the reconstructed complete file."""
        try:
            # Create model folder in final output
            model_folder = self.final_output_path / file_info['model']
            ensure_directory(model_folder)
            
            # Save the reconstructed file
            output_file = model_folder / f"{file_info['filename']}.php"
            
            with open(output_file, 'w', encoding='utf-8') as f:
                # Write clean PHP code without metadata header
                f.write(code)
            
            print(f"   SAVED: {output_file}")
            return True
            
        except Exception as e:
            print(f"   ERROR saving file: {e}")
            return False
    
    def reconstruct_all_files(self):
        """Reconstruct all chunked files found."""
        print("🔧 Starting file reconstruction...")
        
        chunked_files = self.find_chunked_files()
        
        if not chunked_files:
            print("No chunked files found to reconstruct")
            return
        
        print(f"Found {len(chunked_files)} chunked files to reconstruct:")
        for file_info in chunked_files:
            print(f"   {file_info['model']}/{file_info['filename']}.php ({file_info['chunk_count']} chunks)")
        
        successful = 0
        failed = 0
        
        for file_info in chunked_files:
            if self.reconstruct_file(file_info):
                successful += 1
            else:
                failed += 1
        
        print(f"\n🎉 Reconstruction completed!")
        print(f"✅ Successfully reconstructed: {successful} files")
        print(f"❌ Failed to reconstruct: {failed} files")
        
        if successful > 0:
            print(f"\n📁 Reconstructed files saved to: {self.final_output_path}")
            for model_folder in sorted(self.final_output_path.iterdir()):
                if model_folder.is_dir():
                    php_files = list(model_folder.glob('*.php'))
                    print(f"   {model_folder.name}/ ({len(php_files)} files)")
