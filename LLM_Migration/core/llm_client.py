"""
Multi-Provider LLM Client
Handles API calls to different LLM providers (OpenRouter, Google AI).
"""

import json
import requests
import time
from typing import Dict, Any


class MultiProviderClient:
    """Simplified multi-provider LLM client with automatic provider detection."""
    
    # Provider detection patterns (data-driven approach)
    PROVIDER_PATTERNS = {
        'google': ['gemini', 'palm', 'bard'],
        'anthropic': ['claude'],
        'openrouter': ['anthropic', 'openai', 'meta', 'mistral', 'cohere', 
                      'deepseek', 'qwen', 'dolphin', 'nous', 'microsoft']
    }
    
    # Default configurations
    DEFAULT_CONFIG = {
        'max_tokens': {'google': 16000, 'openrouter': 25000, 'anthropic': 16000},
        'temperature': 0.3,
        'timeout': 300
    }
    
    def __init__(self, providers: Dict[str, Any]):
        """Initialize with provider configuration."""
        self.providers = providers
    
    def detect_provider(self, model_name: str) -> str:
        """Detect provider using pattern matching."""
        model_lower = model_name.lower()
        
        # Check for Google AI patterns
        if any(keyword in model_lower for keyword in self.PROVIDER_PATTERNS['google']):
            return 'google'
        
        # Check for Anthropic patterns (direct Claude models)
        if (any(keyword in model_lower for keyword in self.PROVIDER_PATTERNS['anthropic']) and 
            '/' not in model_name):  # Direct Claude models, not OpenRouter proxy
            return 'anthropic'
        
        # Check for OpenRouter patterns (including slash notation)
        if ('/' in model_name or 
            any(pattern in model_lower for pattern in self.PROVIDER_PATTERNS['openrouter'])):
            return 'openrouter'
        
        return 'openrouter'  # Default fallback
    
    def make_api_call(self, model_name: str, prompt: str, **kwargs) -> Dict[str, Any]:
        """Unified API call with error handling."""
        provider = self.detect_provider(model_name)
        
        # Check provider availability
        if not self.providers.get(provider, {}).get('enabled'):
            return self._error_response(f'Provider {provider} is not enabled')
        
        print(f"🔗 Using {provider.upper()} provider for {model_name}")
        
        # Route to appropriate provider method
        try:
            if provider == 'google':
                return self._call_google(model_name, prompt, **kwargs)
            elif provider == 'anthropic':
                return self._call_anthropic(model_name, prompt, **kwargs)
            else:  # openrouter
                return self._call_openrouter(model_name, prompt, **kwargs)
        except Exception as e:
            return self._error_response(str(e))
    
    def _call_openrouter(self, model_name: str, prompt: str, **kwargs) -> Dict[str, Any]:
        """OpenRouter API call with retry logic for rate limits."""
        from core.config import RATE_LIMIT_CONFIG, FREE_MODEL_PATTERNS
        
        # Determine if this is a free model
        is_free = any(pattern in model_name.lower() for pattern in FREE_MODEL_PATTERNS)
        config_key = 'free_models' if is_free else 'paid_models'
        rate_config = RATE_LIMIT_CONFIG['openrouter'][config_key]
        
        payload = {
            "model": model_name,
            "messages": [{"role": "user", "content": prompt}],
            "max_tokens": kwargs.get('max_tokens', self.DEFAULT_CONFIG['max_tokens']['openrouter']),
            "temperature": kwargs.get('temperature', self.DEFAULT_CONFIG['temperature'])
        }
        
        headers = {
            "Authorization": f"Bearer {self.providers['openrouter']['api_key']}",
            "Content-Type": "application/json",
            "HTTP-Referer": "https://github.com/research-project",
            "X-Title": "LLM PHP Migration Research"
        }
        
        # Retry logic for rate limits
        for attempt in range(rate_config['max_retries'] + 1):
            try:
                response = requests.post(
                    "https://openrouter.ai/api/v1/chat/completions",
                    headers=headers,
                    data=json.dumps(payload),
                    timeout=self.DEFAULT_CONFIG['timeout']
                )
                
                if response.status_code == 429:
                    if attempt < rate_config['max_retries']:
                        retry_delay = rate_config['retry_delay'] * (2 ** attempt)  # Exponential backoff
                        print(f"⏳ Rate limited (429). Retrying in {retry_delay:.1f}s... (attempt {attempt + 1}/{rate_config['max_retries'] + 1})")
                        time.sleep(retry_delay)
                        continue
                    else:
                        return self._error_response(f'HTTP 429: Rate limit exceeded after {rate_config["max_retries"]} retries. {response.text[:500]}')
                
                if response.status_code != 200:
                    return self._error_response(f'HTTP {response.status_code}: {response.text[:500]}')
                
                result = response.json()
                return self._success_response(
                    content=result['choices'][0]['message']['content'],
                    provider='openrouter',
                    model=model_name,
                    usage=result.get('usage', {})
                )
                
            except requests.exceptions.RequestException as e:
                if attempt < rate_config['max_retries']:
                    retry_delay = rate_config['retry_delay']
                    print(f"⏳ Request failed. Retrying in {retry_delay:.1f}s... (attempt {attempt + 1}/{rate_config['max_retries'] + 1})")
                    time.sleep(retry_delay)
                    continue
                else:
                    return self._error_response(f'Request failed after {rate_config["max_retries"]} retries: {str(e)}')
        
        return self._error_response('Unexpected error in OpenRouter API call')
    
    def _call_google(self, model_name: str, prompt: str, **kwargs) -> Dict[str, Any]:
        """Google AI API call."""
        client = self.providers['google']['client']
        
        response = client.models.generate_content(
            model=model_name,
            contents=prompt,
            config={
                'temperature': kwargs.get('temperature', self.DEFAULT_CONFIG['temperature']),
                'max_output_tokens': kwargs.get('max_tokens', self.DEFAULT_CONFIG['max_tokens']['google']),
                'top_p': 0.95,
                'top_k': 40
            }
        )
        
        if not response.text:
            return self._error_response('Empty response from Google AI')
        
        # Extract usage info safely
        usage_info = {}
        if hasattr(response, 'usage_metadata'):
            try:
                usage_info = {
                    'prompt_tokens': getattr(response.usage_metadata, 'prompt_token_count', 0),
                    'completion_tokens': getattr(response.usage_metadata, 'candidates_token_count', 0)
                }
            except:
                pass
        
        return self._success_response(
            content=response.text,
            provider='google',
            model=model_name,
            usage=usage_info
        )
    
    def _call_anthropic(self, model_name: str, prompt: str, **kwargs) -> Dict[str, Any]:
        """Anthropic API call."""
        client = self.providers['anthropic']['client']
        
        try:
            response = client.messages.create(
                model=model_name,
                max_tokens=kwargs.get('max_tokens', self.DEFAULT_CONFIG['max_tokens']['anthropic']),
                temperature=kwargs.get('temperature', self.DEFAULT_CONFIG['temperature']),
                messages=[
                    {"role": "user", "content": prompt}
                ]
            )
            
            # Extract content from response
            content = ""
            if hasattr(response, 'content') and response.content:
                # Handle different content types
                if isinstance(response.content, list):
                    content = "".join([block.text if hasattr(block, 'text') else str(block) 
                                     for block in response.content])
                else:
                    content = str(response.content)
            
            if not content:
                return self._error_response('Empty response from Anthropic')
            
            # Extract usage info
            usage_info = {}
            if hasattr(response, 'usage'):
                usage_info = {
                    'prompt_tokens': getattr(response.usage, 'input_tokens', 0),
                    'completion_tokens': getattr(response.usage, 'output_tokens', 0),
                    'total_tokens': getattr(response.usage, 'input_tokens', 0) + getattr(response.usage, 'output_tokens', 0)
                }
            
            return self._success_response(
                content=content,
                provider='anthropic',
                model=model_name,
                usage=usage_info
            )
            
        except Exception as e:
            return self._error_response(f'Anthropic API error: {str(e)}')
    
    def _success_response(self, content: str, provider: str, model: str, usage: Dict[str, Any]) -> Dict[str, Any]:
        """Standardized success response."""
        return {
            'success': True,
            'content': content,
            'provider': provider,
            'model': model,
            'usage': usage
        }
    
    def _error_response(self, error_message: str) -> Dict[str, Any]:
        """Standardized error response."""
        return {'success': False, 'error': error_message}
    
    def test_provider_detection(self):
        """Test provider detection with sample models."""
        test_models = [
            'gemini-1.5-pro',
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'anthropic/claude-3.5-sonnet', 
            'meta-llama/llama-3.1-8b-instruct',
            'gemini-1.5-flash'
        ]
        
        print("\n🔍 Provider Detection Test:")
        for model in test_models:
            provider = self.detect_provider(model)
            print(f"   {model} → {provider.upper()}")
