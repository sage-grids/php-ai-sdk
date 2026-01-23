# Product Discovery: PHP-AI-SDK

## Target Audience & Context

### Product Idea
A PHP Composer package (`sage-grids/php-ai-sdk`) that provides a unified, developer-friendly interface for interacting with multiple AI providers (OpenAI, Google/Gemini, OpenRouter, ElevenLabs, Tavily). The architecture and API should closely mirror the popular JavaScript AI-SDK (ai-sdk.dev).

### Business Objective
Create a PHP library that fills the gap in the PHP ecosystem for a unified AI SDK, capturing developers who want the same excellent DX they get from the JavaScript AI-SDK but in PHP applications.

### Key Assumptions
- PHP developers integrating AI are currently using fragmented, provider-specific SDKs
- There's demand for a unified abstraction layer similar to what JavaScript developers enjoy
- Laravel/Symfony developers represent a significant target segment
- The JavaScript AI-SDK's API design is transferable to PHP idioms

---

## User Segments & Personas

### Segment 1: PHP Backend Developer (Mid-Level)
**Profile:** 3-5 years experience, works on Laravel/Symfony applications, familiar with Composer, wants to add AI features to existing projects.

**Goals:**
- Quickly integrate AI capabilities without deep AI/ML expertise
- Use familiar PHP patterns and tooling
- Minimize time spent reading provider-specific documentation

**Pains:**
- Each AI provider has different SDK patterns and authentication methods
- Switching providers requires significant code changes
- Lack of good PHP examples compared to JavaScript/Python ecosystems

### Segment 2: Tech Lead / Architect
**Profile:** 7+ years experience, makes technology decisions, concerned with maintainability, testing, and vendor lock-in.

**Goals:**
- Choose tools that reduce long-term technical debt
- Enable team to work efficiently across AI providers
- Maintain flexibility to switch providers based on cost/performance

**Pains:**
- Vendor lock-in when using provider-specific SDKs
- Inconsistent error handling across different libraries
- Difficult to mock/test AI integrations

### Segment 3: Indie Developer / Small Agency
**Profile:** Full-stack developer, often solo or small team, builds client projects, values speed and simplicity.

**Goals:**
- Ship AI features quickly to clients
- Minimize learning curve for each new AI capability
- Keep dependencies manageable

**Pains:**
- Limited time to learn multiple AI provider APIs
- Documentation often assumes JavaScript/Python knowledge
- Hard to find PHP-specific AI integration examples

### Segment 4: Enterprise PHP Developer
**Profile:** Works in large organization, strict compliance requirements, needs audit trails and observability.

**Goals:**
- Integrate AI while meeting security/compliance requirements
- Standardize AI usage across teams
- Monitor costs and usage across providers

**Pains:**
- Enterprise features (logging, retry policies, rate limiting) often missing from PHP AI libraries
- Difficult to enforce consistent patterns across large codebases
- Lack of TypeScript-like type safety in PHP AI libraries

---

## Jobs-To-Be-Done (JTBD)

### Segment 1: PHP Backend Developer

1. "When I need to add a chatbot to my Laravel app, I want to call a simple function with my prompt, so I can get AI responses without learning provider-specific APIs."

2. "When I want to stream AI responses to the frontend, I want a straightforward streaming API, so I can provide real-time feedback to users."

3. "When I need to generate images from text descriptions, I want to use the same SDK pattern as text generation, so I can work efficiently without context-switching."

4. "When I need to extract structured data from unstructured text, I want to define a schema and get validated objects back, so I can use the data reliably in my application."

### Segment 2: Tech Lead / Architect

5. "When I'm evaluating AI providers for cost/performance, I want to switch providers with minimal code changes, so I can optimize without major refactoring."

6. "When I need to test AI integrations, I want easily mockable interfaces, so I can write reliable unit tests."

7. "When onboarding new developers, I want them to learn one API pattern, so they can work with any AI provider immediately."

### Segment 3: Indie Developer / Small Agency

8. "When a client requests AI features, I want copy-paste-ready examples, so I can deliver quickly without deep research."

9. "When I need text-to-speech for an accessibility feature, I want to use the same SDK I use for chat, so I don't need to learn another library."

10. "When I want to build an AI agent with tools, I want a clear pattern for tool definitions and execution, so I can create sophisticated interactions."

### Segment 4: Enterprise Developer

11. "When debugging production AI issues, I want comprehensive logging and observability hooks, so I can diagnose problems quickly."

12. "When managing AI costs, I want usage tracking built into the SDK, so I can monitor and allocate expenses."

---

## Problem Statements

| # | Problem Statement | Impact | Confidence |
|---|-------------------|--------|------------|
| 1 | PHP developers struggle with **fragmented AI provider SDKs**, which leads to **increased development time** and **inconsistent code patterns** across projects. | High | Medium |
| 2 | PHP developers lack **streaming support** comparable to JavaScript/Python, which leads to **poor user experience** in real-time AI applications. | High | Medium |
| 3 | Tech leads face **vendor lock-in** when using provider-specific SDKs, which leads to **costly migrations** when switching providers. | High | High |
| 4 | PHP developers struggle to find **quality examples and documentation** for AI integrations, which leads to **longer onboarding times** and **more bugs**. | Medium | High |
| 5 | Enterprise teams lack **standardized observability** in PHP AI libraries, which leads to **difficult debugging** and **unpredictable costs**. | Medium | Medium |
| 6 | PHP developers building AI agents struggle with **tool/function calling patterns**, which leads to **inconsistent implementations** and **maintenance burden**. | Medium | Medium |
| 7 | Developers need to handle **structured output generation** (JSON schemas) differently per provider, which leads to **duplicated validation logic**. | Medium | Medium |
| 8 | PHP developers lack a **unified approach to multimodal inputs** (text + images + files), which leads to **complex conditional code** per provider. | Medium | Low |
| 9 | The existing competitor (`wordpress/php-ai-client`) is **WordPress-focused**, which leads to **awkward integration** for Laravel/Symfony developers. | Medium | Low |
| 10 | PHP developers struggle with **MCP (Model Context Protocol) integration**, which leads to **inability to leverage emerging AI tooling standards**. | Low | Low |

---

## Competitive Analysis & Differentiation

### Competitor: `wordpress/php-ai-client`
**Strengths:**
- WordPress ecosystem integration
- Backed by WordPress/Automattic

**Weaknesses:**
- WordPress-centric design patterns
- Limited to chat/completion use cases
- No streaming support (needs verification)
- Not aligned with ai-sdk.dev patterns

### Differentiation Opportunities for `sage-grids/php-ai-sdk`

| Opportunity | Value | Effort |
|-------------|-------|--------|
| **AI-SDK API alignment** - Mirror JavaScript AI-SDK patterns (`generateText`, `streamText`, `generateObject`, etc.) | High | Medium |
| **Framework-agnostic** - Works equally well in Laravel, Symfony, vanilla PHP | High | Low |
| **First-class streaming** - Generator-based streaming with proper chunk handling | High | Medium |
| **Structured output** - Schema-based object generation with validation | High | Medium |
| **Tool/Function calling** - Clean abstractions for AI tool definitions and execution | High | Medium |
| **MCP support** - Model Context Protocol integration for emerging standards | Medium | High |
| **Multi-provider from day one** - OpenAI, Gemini, OpenRouter, ElevenLabs, Tavily | High | High |
| **PHP 8.1+ features** - Leverage enums, attributes, named arguments, readonly properties | Medium | Low |
| **Comprehensive type hints** - Full PHPStan/Psalm compatibility | Medium | Low |

---

## Assumptions & Open Questions

### Assumptions (to validate)
1. **A1:** PHP developers want ai-sdk.dev-style API (vs. preferring native PHP idioms)
2. **A2:** Streaming is a must-have, not nice-to-have, for most use cases
3. **A3:** Laravel/Symfony developers are the primary audience (vs. WordPress)
4. **A4:** Developers prefer one unified package over separate provider packages
5. **A5:** MCP (Model Context Protocol) will gain traction in the PHP ecosystem

### Open Questions
1. What percentage of PHP AI users are on Laravel vs. Symfony vs. vanilla PHP?
2. How do PHP developers currently handle AI streaming (SSE, WebSockets, polling)?
3. What's the adoption rate of `wordpress/php-ai-client` outside WordPress?
4. Is there demand for synchronous-only (no streaming) usage for simpler use cases?
5. Should we support PHP 8.0 or require 8.1+ for modern features?
6. What logging/observability patterns do enterprise PHP teams expect?

---

## Recommended Validation Next Steps

### Phase 1: Desk Research (1-2 days)
- [ ] Analyze GitHub stars/issues on `wordpress/php-ai-client` and similar libraries
- [ ] Survey Reddit r/PHP, Laravel forums for AI integration discussions
- [ ] Review Packagist download stats for existing AI packages
- [ ] Document exact API surface of JavaScript AI-SDK for PHP translation

### Phase 2: User Interviews (3-5 interviews)
- [ ] Interview Laravel developers who've integrated OpenAI
- [ ] Interview agency developers building AI features for clients
- [ ] Ask about pain points, current workflows, ideal developer experience

### Phase 3: Prototype Validation
- [ ] Build minimal `generateText` / `streamText` implementation
- [ ] Share with 2-3 developers for feedback on API design
- [ ] Validate streaming approach works well with Laravel/Symfony

### Evidence That Would Increase Confidence
| Problem | Validation Method |
|---------|-------------------|
| Fragmented SDKs | Count GitHub issues mentioning "switch provider" |
| Streaming needs | Survey: "Do you need real-time AI responses?" |
| Vendor lock-in | Interview: "Have you switched AI providers? Pain points?" |
| Documentation gap | Analyze Stack Overflow PHP+AI questions vs. JS+AI |

---

## Summary

The PHP ecosystem has a clear gap for a unified, well-designed AI SDK. The JavaScript AI-SDK's success validates the demand for this abstraction layer. Key differentiators for `sage-grids/php-ai-sdk`:

1. **API alignment with ai-sdk.dev** - Leverage familiar patterns
2. **Framework-agnostic design** - Not tied to WordPress or any CMS
3. **Modern PHP features** - PHP 8.1+, full type safety
4. **Comprehensive provider support** - Text, image, speech, search from day one
5. **First-class streaming** - Generator-based, memory-efficient
6. **Tool/Agent patterns** - Support for sophisticated AI applications

The primary risk is adoption - PHP developers may continue using provider-specific SDKs if the value proposition isn't immediately clear. Strong documentation, copy-paste examples, and Laravel/Symfony-specific guides will be critical for success.
