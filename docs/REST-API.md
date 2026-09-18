# REST API

Namespace `jev/v1`. All routes need a logged-in user and the cookie nonce
that `wp.apiFetch` sends automatically; from outside the admin, use an
application password. The API key never leaves the server.

## `POST /wp-json/jev/v1/ask`

Capability: the settings screen's **REST capability** (default `edit_posts`),
filterable with `jevc_rest_capability`.

| Param | Type | Required | Notes |
| --- | --- | --- | --- |
| `state` | any | yes | String, object or array to evaluate |
| `questions` | object | yes | Map of id → question; same shape as the PHP builders produce |
| `model` | string | no | Overrides the default model for this call |

```js
await wp.apiFetch( {
    path: '/jev/v1/ask',
    method: 'POST',
    data: {
        state: { title, body },
        questions: {
            technical: { type: 'noul', instructions: 'Does this assume programming knowledge?' },
            audience:  {
                type: 'choice',
                instructions: 'Who is this for?',
                criteria: { practitioner: 'Will act on it', executive: 'Funds it', general: 'Curious' },
            },
            evidence: {
                type: 'score',
                instructions: 'How well supported is the claim?',
                criteria: [ 'Assertion', 'Anecdote', 'Cited sources' ],
            },
        },
    },
} );
```

Response is the decoded TypeSafe body, untouched:

```json
{
  "model": "jev-latest",
  "answers": {
    "technical": { "noul": 0.91 },
    "audience":  { "choice": "practitioner", "confidence": 0.74,
                   "probabilities": { "practitioner": 0.74, "executive": 0.2, "general": 0.06 } },
    "evidence":  { "score": 1.6, "confidence": 0.8,
                   "probabilities": { "0": 0.1, "1": 0.2, "2": 0.7 },
                   "legend": { "0": "Assertion", "1": "Anecdote", "2": "Cited sources" } }
  },
  "usage": { "input_tokens": 412, "output_tokens": 9 }
}
```

Identical requests are answered from the cache for an hour; the body is the
same either way.

## `GET /wp-json/jev/v1/status`

Same capability. Returns `{ "ready": bool, "model": string, "version": string }`.
`ready` is whether a key resolves; it makes no request.

## Term-suggestion routes

Registered only when the **Term suggestions** module is switched on.
Capability: `edit_post` for the specific `post_id`.

### `POST /wp-json/jev/v1/suggest-terms`

`{ "post_id": int }` → `{ "suggestions": [ { "term_id", "name", "probability" } ] }`,
most likely first, only those at or above the module's threshold. At most 40
terms are asked about, ordered by use. It never creates a term.

### `POST /wp-json/jev/v1/apply-terms`

`{ "post_id": int, "term_ids": int[] }` → `{ "applied": int[], "taxonomy": string }`.
Appends the terms to the post (existing terms are kept). This is the only
route that writes.

## Error codes

Errors come back in the standard WordPress shape:
`{ "code", "message", "data": { "status" } }`.

| Code | HTTP | Meaning |
| --- | --- | --- |
| `rest_forbidden` | 401/403 | Not logged in, or missing the capability |
| `jevc_not_configured` | 503 | No API key resolves. No request was made |
| `jevc_invalid_question` | 400 | Empty map, missing id/type/instructions, or unknown type |
| `jevc_encode_failed` | 400 | The payload could not be JSON-encoded |
| `jevc_invalid_response` | 400 | TypeSafe returned something that was not JSON |
| `jevc_http_<status>` | the API's status | TypeSafe rejected the call. `data.detail` holds the decoded body |
| `jevc_request_failed` | 400 | Transport failure after retries |
| `jevc_no_post` | 404 | `post_id` does not exist (suggest-terms) |
| `jevc_no_terms` | 400 | Empty `term_ids` (apply-terms) |

`429`, `529`, `500`, `502`, `503` and `504` from TypeSafe are retried up to
three times with backoff before surfacing as `jevc_http_<status>`. On the
suggest-terms route any client error is returned with HTTP `502`.
