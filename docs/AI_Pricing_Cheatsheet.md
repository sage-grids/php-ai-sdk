# AI Pricing Cheatsheet

Reference for AI model pricing and usage when integrating with providers (OpenAI, Anthropic, Google, OpenRouter, etc.).  
*Source: [laravel-uniformed-ai SERVICE_PRICING.md](https://github.com/iSerter/laravel-uniformed-ai/blob/445249f2fa2b614cbc0db10618d101ddfb733da6/docs/SERVICE_PRICING.md).*

---

## Pricing resolution (typical)

When matching pricing to a request:

1. **Exact match:** Provider + Service Type + Model Name  
2. **Global match:** Provider + Model Name  
3. **Wildcard match:** Provider + Model prefix*

---

## State of the art model pricing (Mar 2026)

- **Prices:** USD per 1 million tokens.  
- **Tiers:** “Low” = usage below threshold (e.g. 128k or 200k tokens), “High” = above threshold.

| Provider   | Model                                   | Input Cost / 1M | Output Cost / 1M | Tiers / Notes |
| :--------- | :-------------------------------------- | :--------------- | :--------------- | :------------ |
| **OpenAI** | `gpt-5.2`                               | $1.75            | $14.00           | Standard rate |
| **OpenAI** | `gpt-5.2-pro`                           | $21.00           | $168.00          | High-reasoning model |
| **OpenAI** | `gpt-5.4`                               | $2.50 / $5.00    | $15.00 / $22.50  | **Tiered:** (≤272k / >272k); [OpenAI pricing](https://developers.openai.com/api/docs/pricing) |
| **OpenAI** | `gpt-5.4-pro`                           | $30.00 / $60.00  | $180.00 / $270.00 | **Tiered:** (≤272k / >272k); reasoning tokens as output |
| **Anthropic** | `claude-opus-4.6`                    | $5.00            | $25.00           | [Claude API pricing](https://claude.com/pricing#api); prompt caching available |
| **Anthropic** | `claude-sonnet-4.6`                  | $3.00            | $15.00           | Same source |
| **Anthropic** | `claude-haiku-4.5`                   | $1.00            | $5.00            | Same source; fastest, most cost-efficient |
| **Google** | `gemini-3.1-pro-preview`                | $2.00 / $4.00    | $12.00 / $18.00  | **Tiered:** (≤200k / >200k); output includes thinking tokens |
| **Google** | `gemini-3.1-pro-preview-customtools`    | $2.00 / $4.00    | $12.00 / $18.00  | Same as 3.1 Pro Preview |
| **Google** | `gemini-3.1-flash-lite-preview`         | $0.25            | $1.50            | Cost-efficient; text/image/video input (audio $0.50) |
| **Google** | `gemini-3.1-flash-image-preview`        | $0.50            | $3.00            | Text/thinking; image output priced separately |
| **Google** | `gemini-3-flash-preview`                | $0.50            | $3.00            | High speed, low cost; output includes thinking |
| **DeepSeek** | `deepseek-v3.2`                       | $0.25            | $0.38            | Extremely competitive pricing |
| **Qwen**   | `qwen3-max`                             | $1.20 / $3.00    | $6.00 / $15.00   | **Tiered:** (≤128k / >128k) |
| **xAI**    | `grok-4.20-multi-agent-beta-0309`       | $2.00            | $6.00            | 2M context; [xAI models](https://docs.x.ai/developers/models); cached input $0.20 |
| **xAI**    | `grok-4.20-beta-0309-reasoning`         | $2.00            | $6.00            | Same as 4.20 multi-agent |
| **xAI**    | `grok-4.20-beta-0309-non-reasoning`     | $2.00            | $6.00            | Same as 4.20, no reasoning |
| **xAI**    | `grok-4-fast`                            | $0.40            | $1.00            | OpenRouter |
| **xAI**    | `grok-4.1-fast`                          | $0.20            | $0.50            | 2M context window |
| **xAI**    | `grok-code-fast-1`                      | $0.20            | $1.50            | 256K context window |

*Claude 4.6 / Haiku 4.5: [Claude API pricing](https://claude.com/pricing#api). GPT 5.4: [OpenAI API pricing](https://developers.openai.com/api/docs/pricing). Grok 4.20: [xAI models & pricing](https://docs.x.ai/developers/models). Google Gemini 3.1: [Gemini API pricing](https://ai.google.dev/gemini-api/docs/pricing#gemini-3.1-pro-preview) (Mar 2026). Other: OpenRouter/Jan 2026. Gemini 3 Pro Preview deprecated March 9, 2026; use Gemini 3.1 Pro Preview.*

---

## Token usage reference

Rough estimates for cost and usage:

- **~1 page of text** ≈ 600 tokens  
- **~100 lines of code** ≈ 1000 tokens  

Use these when planning usage and budget.
