"""
Output Processing System
Handles parsing of LLM responses and file reconstruction from chunks.
"""

import re
from pathlib import Path
from typing import Dict, List, Any, Optional, Tuple
import sys

# Add parent directory to path for config import
sys.path.insert(0, str(Path(__file__).parent.parent.parent))
from config import MODEL_OUTPUT_DIR, LLM_MIGRATION_SUBDIR

from core.utils import ensure_directory


class OutputParser:
    """Parses model responses and extracts migrated code."""
    
    def __init__(self):
        self.model_output_path = MODEL_OUTPUT_DIR
        self.parsed_path = LLM_MIGRATION_SUBDIR / 'outputs' / 'new-version'
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
            with open(response_file, 'r', encoding='utf-8') as f:
                content = f.read()
            
            metadata = self.extract_metadata(content)
            migrated_code = self.extract_migrated_code(content)
            
            if not metadata.get('original_file'):
                return {'success': False}
            
            if not migrated_code:
                return {'success': False}
            
            return {
                'success': True,
                'metadata': metadata,
                'migrated_code': migrated_code
            }
        
        except Exception as e:
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
            
            return True
            
        except Exception as e:
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
        if not self.model_output_path.exists():
            return
        
        # Look for model subfolders
        model_folders = [d for d in self.model_output_path.iterdir() if d.is_dir()]
        
        if not model_folders:
            # Fallback: process files directly (old structure)
            response_files = list(self.model_output_path.glob('*.txt'))
            if response_files:
                success, failed = self._process_response_files(response_files)
            return
        
        total_success = 0
        total_failed = 0
        
        # Process each model folder
        for model_folder in model_folders:
            response_files = list(model_folder.glob('*.txt'))
            
            if not response_files:
                continue
            
            success_count, failed_count = self._process_response_files(response_files, model_folder.name)
            
            total_success += success_count
            total_failed += failed_count


class FileReconstructor:
    """Reconstructs complete files from parsed chunk files."""
    
    def __init__(self, parser: OutputParser):
        self.parser = parser
        self.chunked_output_path = CHUNKED_MODEL_OUTPUT_DIR
        self.final_output_path = LLM_MIGRATION_SUBDIR / 'outputs' / 'new-version'
        ensure_directory(self.final_output_path)
    
    def find_chunked_files(self) -> List[Dict[str, Any]]:
        """Find all chunked file directories."""
        if not self.chunked_output_path.exists():
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
                pass
        
        # Sort by chunk number
        chunk_files.sort(key=lambda x: x[0])
        return chunk_files
    
    def reconstruct_file(self, file_info: Dict[str, Any]) -> bool:
        """Reconstruct a complete file from its chunks."""
        # Get sorted chunk files
        chunk_files = self.get_chunk_files(file_info['directory'])
        
        if not chunk_files:
            return False
        
        # Check for missing chunks
        expected_numbers = list(range(1, len(chunk_files) + 1))
        actual_numbers = [num for num, _ in chunk_files]
        missing = set(expected_numbers) - set(actual_numbers)
        
        # Parse each chunk
        parsed_chunks = []
        metadata = None
        
        for chunk_num, chunk_file in chunk_files:
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
            else:
                parsed_chunks.append({
                    'number': chunk_num,
                    'code': None,
                    'metadata': None
                })
        
        if not any(chunk['code'] for chunk in parsed_chunks):
            return False
        
        # Combine chunks
        combined_code = []
        
        for chunk in parsed_chunks:
            if chunk['code']:
                combined_code.append(chunk['code'])
            else:
                combined_code.append(f"// ERROR: Chunk {chunk['number']} failed to parse")
        
        final_code = ''.join(combined_code)
        
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
            
            return True
            
        except Exception as e:
            return False
    
    def reconstruct_all_files(self):
        """Reconstruct all chunked files found."""
        chunked_files = self.find_chunked_files()
        
        if not chunked_files:
            return
        
        successful = 0
        failed = 0
        
        for file_info in chunked_files:
            if self.reconstruct_file(file_info):
                successful += 1
            else:
                failed += 1
