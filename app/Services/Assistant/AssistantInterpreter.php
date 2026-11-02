<?php

namespace App\Services\Assistant;

use Carbon\Carbon;

/** Conservative local parsing, with unambiguous server-provided names only. */
final class AssistantInterpreter
{
    public function isUnsupportedBusinessAction(string $message): bool
    {
        return (bool) preg_match('/\b(delete|remove|refund|pay|withdraw|score|seed|regenerate|replace)\b/i', $message);
    }

    public function unsupportedConstraint(string $message): ?string
    {
        if (preg_match('/\bcreate\s+(?:a\s+)?(?:new\s+)?(?:individual\s+)?draw\s+(?:named|called)\s+/i', $message)) {
            return null;
        }
        if (preg_match('/\b(move|shift|keep|except|exclude|avoid|free|without|unavailable|blocked|reserved|closed|leave|leaving|before|after|finals?|semifinals?|semi-finals?|not|never)\b|\ball\s+but\b|\bround\s+\d|\bno\s+(?:matches|play|later|earlier)|\bdon[’\x27]t\b|\bcourts?\s+\d+\s*(?:to|through|-)\s*\d+\s*(?:,|and)\s*\d/i', $message)) {
            return 'That request contains a match-level move, exclusion or reservation that this assistant cannot apply yet. Use the existing schedule calendar for that change, or describe a whole-draw date, court selection and start/finish time. I have not prepared a partial interpretation.';
        }

        return null;
    }

    public function interpret(string $message, array $context, array $previous = []): array
    {
        if ($this->isUnsupportedBusinessAction($message)) {
            return ['action' => 'help', 'reason_code' => 'business_action_not_supported', 'clarification' => 'I can preview existing-match schedules, review publication and create a new draft draw. Use the existing draw guide for seeding or generation.'];
        }
        if ($reason = $this->unsupportedConstraint($message)) {
            return ['action' => 'help', 'clarification' => $reason, 'reason_code' => 'unsupported_constraint'];
        }
        $q = mb_strtolower(trim($message));
        if (preg_match('/\bgenerate\b/', $q) && ! preg_match('/\bregenerate\b/', $q)) {
            $draws = $this->matchingDraws($q, $context['draws']);
            if (count($draws) === 1) return ['action' => 'draw_generate', 'options' => ['draw_ids' => $draws]];
            return ['action' => 'help', 'clarification' => 'Choose exactly one prepared, unpublished Round Robin draw in the fields and review initial generation. Other draw formats continue in the draw guide.'];
        }
        if (preg_match('/\bcreate\s+(?:a\s+)?(?:new\s+)?(?:individual\s+)?draw\s+(?:named|called)\s+(.+)/i', $message, $match)) {
            return ['action' => 'draw_draft', 'draw_name' => trim($match[1], " \t\n\r\0\x0B\"'")];
        }
        if (preg_match('/\b(create|set up|setup)\b.*\bdraw\b/', $q)) {
            return ['action' => 'help', 'clarification' => 'What should the new individual draft draw be called? For example: create draw named Under 14 Boys.'];
        }
        $action = preg_match('/\b(publish|publication|public)\b/', $q) ? 'publication' : 'schedule';
        $options = $previous;
        if (preg_match('/\b(20\d\d-\d\d-\d\d)\b/', $q, $match)) {
            $options['date'] = $match[1];
        } elseif (preg_match('/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/', $q, $match)) {
            $dates = array_values(array_filter($context['dates'], fn ($date) => strtolower(Carbon::parse($date)->format('l')) === $match[1]));
            if (count($dates) !== 1) {
                return ['action' => $action, 'options' => $options, 'clarification' => 'Which exact date do you mean? Choose a date within this event.'];
            }
            $options['date'] = $dates[0];
        } elseif (str_contains($q, 'tomorrow') || str_contains($q, 'today')) {
            $options['date'] = now()->addDays(str_contains($q, 'tomorrow') ? 1 : 0)->toDateString();
        }
        if (preg_match('/\b(?:from|start(?:ing)?(?:\s+at)?|at)\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/', $q, $match)) {
            $options['start_time'] = $this->time($match);
        }
        if (preg_match('/\b(?:until|finish(?:ing)?(?:\s+at)?|end(?:ing)?(?:\s+at)?)\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/', $q, $match)) {
            $options['end_time'] = $this->time($match);
        }
        if (preg_match('/\b(\d+)\s*(?:minute|min)s?\s*(?:rest|between)/', $q, $match)
            || preg_match('/\brest\s+(?:of\s+)?(\d+)\s*(?:minute|min)/', $q, $match)) {
            $options['player_rest'] = (int) $match[1];
        } elseif (preg_match('/\b(?:an?|one)\s+hour\s+(?:rest|between)/', $q)) {
            $options['player_rest'] = 60;
        }
        foreach (['duration' => '/\b(?:duration|matches last|match length)\s+(\d+)/', 'court_gap' => '/\b(?:gap|changeover)\s+(\d+)/'] as $key => $pattern) {
            if (preg_match($pattern, $q, $match)) $options[$key] = (int) $match[1];
        }
        $draws = $this->matchingDraws($q, $context['draws']);
        if (! $draws && preg_match('/\b(?:under|u)[\s\/-]*\d+|\b(?:boys|girls|mixed)\b/', $q)) {
            return ['action' => $action, 'options' => $options, 'clarification' => 'I could not identify that draw unambiguously. Choose its exact name in the draw selection.'];
        }
        if ($draws) $options['draw_ids'] = $draws;
        if (preg_match('/\ball\s+(?:the\s+)?draws\b/', $q)) $options['draw_ids'] = array_column($context['draws'], 'id');
        $venues = array_values(array_filter($context['venues'], fn ($venue) => $venue['name'] !== '' && str_contains($q, mb_strtolower($venue['name']))));
        if ($venues) $options['venue_ids'] = array_column($venues, 'id');
        if (preg_match('/\bcourts?\s+(\d+(?:\s*(?:,|and)\s*\d+)+)/', $q, $match)) {
            $options['courts'] = array_values(array_unique(preg_split('/\s*(?:,|and)\s*/', $match[1])));
        } elseif (preg_match('/\bcourts?\s+(\d+)\s*(?:to|through|-)\s*(\d+)/', $q, $match)) {
            if ((int) $match[1] > (int) $match[2] || (int) $match[2] > 100) {
                return ['action' => $action, 'options' => $options, 'clarification' => 'Choose a valid court range from the event venue.'];
            }
            $options['courts'] = array_map('strval', range((int) $match[1], (int) $match[2]));
        } elseif (preg_match('/\bcourt\s+(\d+)\b/', $q, $match)) {
            $options['courts'] = [$match[1]];
        }
        $recognised = preg_match('/\b(schedule|order of play|preview|publish|publication|public|from|start|until|finish|end|rest|duration|gap|changeover|court|tomorrow|today|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/', $q) || $draws;
        if (! $recognised) return ['action' => 'help', 'reason_code' => 'unsupported_request', 'clarification' => 'Tell me which draws and date to schedule, or use the scheduling fields. Nothing changes until you review and confirm a proposal.'];
        return ['action' => $action, 'options' => $options];
    }

    private function matchingDraws(string $q, array $draws): array
    {
        $normalise = fn ($text) => preg_replace('/\s+draw$/', '', trim(preg_replace('/[^a-z0-9]+/', ' ', preg_replace('/\b(?:under|u)\s*[\/-]?\s*(\d+)/', 'under $1', mb_strtolower($text)))));
        $text = ' '.$normalise($q).' ';
        $matches = array_values(array_filter($draws, fn ($draw) => $normalise($draw['name']) !== '' && str_contains($text, ' '.$normalise($draw['name']).' ')));
        return array_column($matches, 'id');
    }

    private function time(array $match): string
    {
        $hour = (int) $match[1];
        $meridiem = $match[3] ?? '';
        if ($meridiem === 'pm' && $hour < 12) $hour += 12;
        if ($meridiem === 'am' && $hour === 12) $hour = 0;
        return sprintf('%02d:%02d', $hour, (int) ($match[2] ?? 0));
    }
}
