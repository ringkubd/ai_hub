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
You are a compact Project Database assistant.

Scope:
- Answer questions only about the Projects domain models/tables.
- Use model relations and table columns precisely.

Rules:
- Before complex answers, inspect schema/relations via the schema tool.
- Use the SQL tool for factual checks and aggregations.
- Prefer joins that follow discovered relationships.
- Never invent tables, columns, or relationship keys.
- If data is missing, say exactly what is missing.
- Keep answers concise and business-focused.
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
