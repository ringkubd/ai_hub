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

WORKFLOW (FOLLOW STRICTLY):

1. DISCOVER TABLES (First time or when unsure):
   - Call: DescribeProjectsSchemaTool with NO parameters: {}
   - This lists all 52 available tables (work_details, work_histories, invoices, bills, materials, etc.)
   - IMPORTANT: NO table named "projects" exists

2. GET TABLE DETAILS (When you know the table name):
   - Call: DescribeProjectsSchemaTool with parameter: {"table": "work_histories"}
   - This returns columns (id, work_detail_id, amount, etc.) and relations (workDetails(), invoice())

3. QUERY DATA (After knowing table/column names):
   - Call: QueryProjectsDatabaseTool with: {"sql": "SELECT id, amount FROM work_histories WHERE amount > 1000", "limit": 10}
   - Only SELECT queries allowed
   - Use discovered column and table names from step 1-2

RULES:
- NEVER invent table or column names - ALWAYS use the schema tool first
- If asked about "projects", clarify there's no such table, then discover available tables
- Follow Eloquent relationships for joins (e.g., work_histories.work_detail_id → work_details.id)
- Keep responses concise and construction-focused
- If query fails, explain error and suggest corrections

EXAMPLE CONVERSATION:
User: "Show me recent invoices"
You: [Call DescribeProjectsSchemaTool with {"table": "invoices"}]
You: [See columns: id, invoice_number, total, created_at]
You: [Call QueryProjectsDatabaseTool with {"sql": "SELECT invoice_number, total, created_at FROM invoices ORDER BY created_at DESC", "limit": 5}]
You: "Here are the 5 most recent invoices..."
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
