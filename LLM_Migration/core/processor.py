"""
Code Processing Engine
Handles file migration, chunking, and API interactions.
"""

import time
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Optional, Any, Union
import sys

# Add parent directory to path for config import
sys.path.insert(0, str(Path(__file__).parent.parent.parent))
from config import MODEL_OUTPUT_DIR, CHUNKED_MODEL_OUTPUT_DIR

from core.config import DEFAULT_CHUNK_SIZE, RATE_LIMIT_CONFIG, FREE_MODEL_PATTERNS
from core.llm_client import MultiProviderClient
from core.prompts import prompt_manager
from core.utils import normalize_model_name, chunk_code, ensure_directory


class MigrationManager:
    """Manages the migration process for PHP files."""
    
    def __init__(self, multi_client: MultiProviderClient, test_files: Dict[str, str]):
        self.multi_client = multi_client
        self.test_files = test_files
        self.last_request_time = 0  # Track timing for rate limiting
    
    def get_rate_limit_delay(self, model_name: str, provider: str) -> float:
        """Get appropriate delay for rate limiting based on model and provider."""
        if provider == 'openrouter':
            is_free = any(pattern in model_name.lower() for pattern in FREE_MODEL_PATTERNS)
            config_key = 'free_models' if is_free else 'paid_models'
            return RATE_LIMIT_CONFIG['openrouter'][config_key]['delay']
        else:
            return RATE_LIMIT_CONFIG.get(provider, {}).get('delay', 1.0)
    
    def apply_rate_limiting(self, model_name: str, provider: str):
        """Apply rate limiting delay between requests."""
        delay = self.get_rate_limit_delay(model_name, provider)
        time_since_last = time.time() - self.last_request_time
        
        if time_since_last < delay:
            sleep_time = delay - time_since_last
            time.sleep(sleep_time)
        
        self.last_request_time = time.time()
    
    @staticmethod
    def save_response(response_data: Dict[str, Any], file_path: Path, metadata: Dict[str, Any] = None):
        """Save API response with consistent metadata format."""
        ensure_directory(file_path.parent)
        
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write("=== RAW MODEL RESPONSE ===\n")
            
            # Write metadata
            if metadata:
                for key, value in metadata.items():
                    f.write(f"{key.capitalize()}: {value}\n")
            
            f.write(f"Length: {len(response_data['content'])} characters\n")
            f.write(f"Usage: {response_data.get('usage', {})}\n")
            f.write(f"Timestamp: {datetime.now()}\n")
            f.write("=" * 50 + "\n\n")
            f.write(response_data['content'])
    
    def process_api_call(self, model_name: str, prompt: str, output_path: Path, metadata: Dict[str, Any]) -> Optional[str]:
        """Unified API call processing with error handling and rate limiting."""
        provider = self.multi_client.detect_provider(model_name)
        
        # Apply rate limiting
        self.apply_rate_limiting(model_name, provider)
        
        # Make API call
        result = self.multi_client.make_api_call(model_name, prompt)
        
        if not result['success']:
            return None
        
        # Validate response
        raw_response = result['content']
        
        if not raw_response or len(raw_response.strip()) < 10:
            return None
        
        # Save response
        metadata['provider'] = result.get('provider', 'unknown').upper()
        self.save_response(result, output_path, metadata)
        
        return raw_response
    
    def migrate_file_single(self, filename: str, original_code: str, model_name: str, strategy: str) -> Optional[str]:
        """Migrate single file using multi-provider client."""
        prompt = prompt_manager.create_prompt(original_code, strategy)
        
        # Create output path
        model_short = normalize_model_name(model_name)
        base_name = filename.replace('.php', '')
        output_file = MODEL_OUTPUT_DIR / model_short / f"{base_name}.txt"
        
        return self.process_api_call(model_name, prompt, output_file, {
            'file': filename, 'model': model_name, 'strategy': strategy
        })
    
    def migrate_file_chunked(self, filename: str, original_code: str, model_name: str, strategy: str, chunk_size: int) -> List[Optional[str]]:
        """Migrate large file using organized chunking."""
        chunks = chunk_code(original_code, chunk_size)
        total_chunks = len(chunks)
        
        # Create organized folder structure
        model_short = normalize_model_name(model_name)
        file_base = filename.replace('.php', '')
        
        file_dir = CHUNKED_MODEL_OUTPUT_DIR / model_short / file_base
        ensure_directory(file_dir)
        
        # Process chunks
        chunk_strategy = f"chunk_{strategy}" if not strategy.startswith('chunk_') else strategy
        all_responses = []
        
        for i, chunk_info in enumerate(chunks, 1):
            # Create prompt and make API call
            prompt = prompt_manager.create_prompt(
                chunk_info['code'], chunk_strategy,
                filename=filename, start_line=chunk_info['start_line'],
                end_line=chunk_info['end_line'], total_lines=chunk_info['total_lines'],
                chunk_number=i, total_chunks=total_chunks
            )
            
            response = self.process_api_call(model_name, prompt, file_dir / f"{i}.txt", {
                'file': filename, 'model': model_name, 'strategy': chunk_strategy, 'chunk': i
            })
            
            all_responses.append(response)
        
        return all_responses
    
    def migrate_file(self, filename: str, model_name: str, strategy: str = "basic", 
                    chunk_size: int = None, auto_chunk: bool = True) -> Union[str, List[Optional[str]], None]:
        """Migrate file with automatic chunking for large files."""
        
        chunk_size = chunk_size or DEFAULT_CHUNK_SIZE
        
        if filename not in self.test_files:
            return None
        
        original_code = self.test_files[filename]
        line_count = len(original_code.split('\n'))
        
        # Decide processing method
        if auto_chunk and line_count > chunk_size:
            return self.migrate_file_chunked(filename, original_code, model_name, strategy, chunk_size)
        else:
            return self.migrate_file_single(filename, original_code, model_name, strategy)
    
    def batch_migrate(self, filenames: List[str], model: str = "gemini-1.5-pro", strategy: str = "basic", 
                     chunk_size: int = None, auto_chunk: bool = True) -> List[Union[str, List[Optional[str]], None]]:
        """Migrate multiple files with automatic chunking and rate limiting."""
        chunk_size = chunk_size or DEFAULT_CHUNK_SIZE
        provider = self.multi_client.detect_provider(model)
        
        results = []
        
        for filename in filenames:
            result = self.migrate_file(filename, model, strategy, chunk_size=chunk_size, auto_chunk=auto_chunk)
            results.append(result)
        
        return results
