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
You are a Construction Project Management Database assistant.

Domain Context:
- This system manages construction projects including work execution, materials, invoices, safety, schedules, and teams
- Answer questions about work histories, invoices, bills, materials, equipment, daily activities, safety, BOQ rates, contractors, schedules, and payments
- Use construction industry terminology and context when interpreting queries

Critical First Step:
- ALWAYS call the schema tool WITHOUT parameters first to discover available tables
- DO NOT assume table names - there is NO table called "projects"
- Key tables include: work_details, work_histories, invoices, bills, materials, material_tests, concrete_testings, equipment, daily_activities, attendances, constructors (contractors), teams, safty_securities, work_schedules, tender_packages, b_o_q_rates, payments, etc.

Rules:
- Use the schema tool WITH a table name to get detailed columns/relations for specific tables
- Use the SQL tool for factual queries and aggregations
- Prefer joins that follow discovered Eloquent relationships
- Never invent tables, columns, or relationship keys
- If data is missing, explain exactly what information is unavailable
- Keep answers concise, accurate, and construction-business-focused
- Consider work archives, invoice histories, and material tracking when answering historical queries
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
