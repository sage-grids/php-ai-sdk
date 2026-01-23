# PHP-AI-SDK : Initial Product Brief

I would like to create AI-SDK for PHP.  It's a composer package/library and it will be called `sage-grids/php-ai-sdk`. 
It'll be similar to https://ai-sdk.dev/ that's for Javascript.
It'd be great if we can make it as similar as possible.
Of course, we can use official client libraries for OpenAI, Gemini, OpenRouter.ai, etc.
But the developer experience / methods / architecture / API should be as similar to https://ai-sdk.dev/ as possible.

In the beginning, It's okay to limit the functionality to: Chat, Text-Completion, Image Generation, Text to Speech, Speech to Text. 

Example functions: 
 - https://ai-sdk.dev/cookbook/node/generate-text
 - https://ai-sdk.dev/cookbook/node/generate-text-with-chat-prompt
 - https://ai-sdk.dev/cookbook/node/generate-text-with-image-prompt
 - https://ai-sdk.dev/cookbook/node/stream-text
 - https://ai-sdk.dev/cookbook/node/stream-text-with-chat-prompt
 - https://ai-sdk.dev/cookbook/node/stream-text-with-image-prompt
 - https://ai-sdk.dev/cookbook/node/stream-text-with-file-prompt
 - https://ai-sdk.dev/cookbook/node/generate-object-reasoning
 - https://ai-sdk.dev/cookbook/node/generate-object
 - https://ai-sdk.dev/cookbook/node/call-tools
 - https://ai-sdk.dev/cookbook/node/call-tools-in-parallel
 - https://ai-sdk.dev/cookbook/node/call-tools-with-image-prompt
 - https://ai-sdk.dev/cookbook/node/call-tools-multiple-steps
 - https://ai-sdk.dev/cookbook/node/mcp-tools
 - https://ai-sdk.dev/cookbook/node/manual-agent-loop
 - https://ai-sdk.dev/cookbook/node/web-search-agent 
 - https://ai-sdk.dev/cookbook/node/mcp-elicitation 
 - https://ai-sdk.dev/cookbook/node/embed-text 

To begin with, we can support these providers: 
- OpenAI (Chat, image generation, whisper, etc.)
- OpenRouter (Chat models)
- Google / Gemini
- ElevenLabs (for text to speech, speech to text services)
- Tavily (for search)
- Replicate.com (can be left to phase 2)


## Competition and Opportunity Gap: 

One competitor for this library is `https://github.com/WordPress/php-ai-client`
. We need to make sure we're differentiating and providing a unique value.
We can use that package in the background if it makes sense and the license allows it, 
However, we must make sure that we're providing unique values that the competitor libraries don't. Aligning with the famous Javascript AI-SDK is one of the values, but we must excel in other areas too. Do comprehensive analysis and opportunity research while creating the PRD / Discovery documents.


## Resources
The below are a list of prominent sources in AI development/integration.
We don't have to use them, but I'm just storing them here as a note.

- https://models.dev/
- https://openrouter.ai/docs/quickstart 
- https://replicate.com/docs 
- https://platform.openai.com/docs/api-reference/introduction
- https://github.com/openai-php/client
