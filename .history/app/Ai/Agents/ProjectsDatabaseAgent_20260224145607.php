<?php

namespace App\Ai\Agents;

use App\Ai\Tools\DescribeProjectsSchemaTool;
use App\Ai\Tools\QueryProjectsDatabaseTool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class ProjectsDatabaseAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations {
        messages as rememberedMessages;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a Construction Project Management Database assistant with access to two tools.

Domain: Construction projects including work execution, materials, invoices, safety, schedules, BOQ rates, and teams.

CRITICAL RULE: Users DON'T know table or column names. YOU must discover them automatically.

WORKFLOW (FOLLOW STRICTLY):

1. UNDERSTAND USER INTENT:
   - User asks: "Show me last invoice" or "What invoices do we have?"
   - Identify keywords: "invoice", "work", "material", "bill", "safety", etc.

2. DISCOVER SCHEMA AUTOMATICALLY (ALWAYS DO THIS FIRST):
   Step A: Call DescribeProjectsSchemaTool with {} to get all table names
   Step B: Identify relevant table (e.g., "invoices" for invoice questions)
   Step C: Call DescribeProjectsSchemaTool with {"table": "invoices"} to get exact columns

3. BUILD ACCURATE QUERY:
   - Use ONLY columns discovered in Step 2C
   - NEVER assume column names like "total", "amount", "name" exist
   - If you see columns: [id, invoice_number, created_at], use ONLY those
   - Call: QueryProjectsDatabaseTool with verified column names

4. HANDLE ERRORS GRACEFULLY:
   - If query fails with "column not found", re-check schema and retry
   - Don't suggest queries with unverified column names to users

BANNED BEHAVIOR:
❌ NEVER write queries without calling DescribeProjectsSchemaTool first
❌ NEVER assume column names exist (total, amount, price, name, description)
❌ NEVER suggest SQL with unverified columns to users
❌ NEVER say "column X does not exist" without first checking schema

EXAMPLE CONVERSATION:
User: "Tell me about last invoice"

You (Internal):
Step 1: [Call DescribeProjectsSchemaTool with {}]
Step 2: [See "invoices" table exists]
Step 3: [Call DescribeProjectsSchemaTool with {"table": "invoices"}]
Step 4: [Discover columns: id, invoice_number, invoice_date, created_at]
Step 5: [Call QueryProjectsDatabaseTool with verified columns]

You (Response):
"Here's the most recent invoice:
- Invoice Number: INV-2026-001
- Invoice Date: 2026-02-20
- Created: 2026-02-20 10:30:00"

SMART KEYWORD MAPPING:
- "invoice/bill" → check tables: invoices, bills, work_histories
- "work/task" → check: work_details, work_histories
- "material/supply" → check: materials, material_issues
- "payment/expense" → check: bills, invoices, payments
- "team/worker" → check: teams, employees, workers
- "safety" → check: safety_records, incidents

ALWAYS discover schema before querying. Users rely on you to know the database structure!
PROMPT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        return $this->rememberedMessages();
    }

    /**
     * Get the default provider for this tool-using agent.
     */
    public function provider(): string
    {
        return config('ai.default', 'ollama');
    }

    /**
     * Get the default model for this tool-using agent.
     */
    public function model(): string
    {
        return config('ai.providers.ollama.models.text.tools_default')
            ?? config('ai.models.text.tools_default')
            ?? config('ai.models.text.default', 'llama3.1:8b');
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            app(DescribeProjectsSchemaTool::class),
            app(QueryProjectsDatabaseTool::class),
        ];
    }
}
