"""
Test script for Claude integration in the multi-provider system.
"""

import os
from core.config import config
from core.llm_client import MultiProviderClient

def test_claude_integration():
    """Test Claude integration with the multi-provider system."""
    
    print("🧪 Testing Claude Integration")
    print("=" * 50)
    
    # Get providers from config
    providers = config.get_providers()
    
    # Initialize client
    client = MultiProviderClient(providers)
    
    # Test provider detection
    client.test_provider_detection()
    
    # Check if Claude/Anthropic is enabled
    if not config.is_provider_enabled('anthropic'):
        print("\n❌ Anthropic provider is not enabled.")
        print("💡 Make sure to set your ANTHROPIC_API_KEY environment variable.")
        return
    
    print(f"\n✅ Anthropic provider is enabled!")
    
    # Test Claude API call
    test_models = [
        'claude-3-5-haiku-20241022'
    ]
    
    test_prompt = "Hello! Please respond with a brief greeting and confirm you're Claude."
    
    for model in test_models:
        print(f"\n🔄 Testing {model}...")
        try:
            response = client.make_api_call(
                model_name=model,
                prompt=test_prompt,
                max_tokens=100,
                temperature=0.7
            )
            
            if response['success']:
                print(f"✅ Success!")
                print(f"📝 Response: {response['content'][:200]}...")
                print(f"📊 Usage: {response.get('usage', 'N/A')}")
            else:
                print(f"❌ Error: {response['error']}")
                
        except Exception as e:
            print(f"❌ Exception: {str(e)}")

if __name__ == "__main__":
    test_claude_integration()
