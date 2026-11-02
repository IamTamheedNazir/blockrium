<?php

namespace App\Http\Controllers;

use App\Services\SquadService;
use Illuminate\Http\Request;

class SquadPublicController extends Controller
{
    private $squads;

    public function __construct(SquadService $squads)
    {
        $this->squads = $squads;
    }

    public function landing(string $code)
    {
        $payload = $this->squads->publicInvitePayload($code);
        abort_unless($payload, 404);

        return view('squad_landing', $payload);
    }

    public function poster(string $code)
    {
        $payload = $this->squads->publicInvitePayload($code);
        abort_unless($payload, 404);

        return response($this->posterSvg($payload), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function resolve(string $code)
    {
        $payload = $this->squads->publicInvitePayload($code);
        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Squad invite not found.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    private function posterSvg(array $payload): string
    {
        $squad = $payload['squad'];
        $poster = $payload['poster'];
        $name = $this->escape($squad['name']);
        $code = $this->escape($squad['invite_code']);
        $country = $this->escape($squad['country_code'] ?: 'Global');
        $capacity = $this->escape($poster['capacity_label']);
        $weekly = number_format((int) $squad['weekly_points']);
        $target = number_format((int) $squad['weekly_target_points']);
        $boost = (int) $squad['community_boost_percent'];
        $shareUrl = $this->escape($squad['share_url']);
        $template = $poster['template'] ?? 'white';
        $isDark = $template === 'dark';
        $isCountry = $template === 'country';
        $isWinner = $template === 'weekly_winner';
        $bg = $isDark ? '#07111f' : '#ffffff';
        $card = $isDark ? '#102039' : '#ffffff';
        $text = $isDark ? '#f8fafc' : '#07111f';
        $muted = $isDark ? '#a8b3c7' : '#64748b';
        $line = $isDark ? '#28415f' : '#dbeafe';
        $softA = $isDark ? '#132a49' : ($isCountry ? '#f0fdf4' : '#ecfeff');
        $softB = $isDark ? '#0b1b31' : ($isWinner ? '#fff7ed' : '#eff6ff');
        $eyebrow = $isWinner ? 'Weekly Winner Poster' : ($isCountry ? 'Country Squad Invite' : 'Community mining team invite');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 1080 1350">
  <defs>
    <linearGradient id="accent" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0" stop-color="#2dd4bf"/>
      <stop offset="1" stop-color="#2563eb"/>
    </linearGradient>
    <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="18" stdDeviation="22" flood-color="#0f172a" flood-opacity=".16"/>
    </filter>
  </defs>
  <rect width="1080" height="1350" fill="$bg"/>
  <circle cx="935" cy="116" r="190" fill="$softA"/>
  <circle cx="90" cy="1220" r="260" fill="$softB"/>
  <rect x="78" y="78" width="924" height="1194" rx="54" fill="$card" stroke="$line" stroke-width="2" filter="url(#shadow)"/>
  <rect x="126" y="126" width="96" height="96" rx="26" fill="url(#accent)"/>
  <text x="174" y="190" text-anchor="middle" font-family="Arial, sans-serif" font-size="54" font-weight="800" fill="#ffffff">B</text>
  <text x="250" y="162" font-family="Arial, sans-serif" font-size="34" font-weight="800" fill="$text">Blockrium Squad</text>
  <text x="250" y="207" font-family="Arial, sans-serif" font-size="24" fill="$muted">$eyebrow</text>
  <text x="126" y="350" font-family="Arial, sans-serif" font-size="76" font-weight="900" fill="$text">$name</text>
  <text x="126" y="420" font-family="Arial, sans-serif" font-size="32" fill="$muted">Join my squad and help unlock weekly activity chests.</text>
  <rect x="126" y="500" width="828" height="170" rx="34" fill="$softB" stroke="$line" stroke-width="2"/>
  <text x="168" y="568" font-family="Arial, sans-serif" font-size="24" font-weight="700" fill="$muted">INVITE CODE</text>
  <text x="168" y="630" font-family="Arial, sans-serif" font-size="58" font-weight="900" letter-spacing="5" fill="#0f766e">$code</text>
  <rect x="126" y="730" width="246" height="150" rx="30" fill="#ecfdf5"/>
  <text x="160" y="790" font-family="Arial, sans-serif" font-size="42" font-weight="900" fill="#065f46">$capacity</text>
  <text x="160" y="838" font-family="Arial, sans-serif" font-size="24" fill="#047857">Squad capacity</text>
  <rect x="417" y="730" width="246" height="150" rx="30" fill="#eff6ff"/>
  <text x="451" y="790" font-family="Arial, sans-serif" font-size="42" font-weight="900" fill="#1d4ed8">$weekly</text>
  <text x="451" y="838" font-family="Arial, sans-serif" font-size="24" fill="#2563eb">of $target points</text>
  <rect x="708" y="730" width="246" height="150" rx="30" fill="#fff7ed"/>
  <text x="742" y="790" font-family="Arial, sans-serif" font-size="42" font-weight="900" fill="#c2410c">$boost%</text>
  <text x="742" y="838" font-family="Arial, sans-serif" font-size="24" fill="#ea580c">Activity boost</text>
  <rect x="126" y="942" width="250" height="250" rx="34" fill="$softB" stroke="$line" stroke-width="2"/>
  <rect x="168" y="984" width="52" height="52" fill="#0f172a"/>
  <rect x="230" y="984" width="52" height="52" fill="#0f172a"/>
  <rect x="292" y="984" width="42" height="42" fill="#0f172a"/>
  <rect x="168" y="1046" width="42" height="42" fill="#0f172a"/>
  <rect x="240" y="1056" width="94" height="32" fill="#0f172a"/>
  <rect x="168" y="1108" width="74" height="42" fill="#0f172a"/>
  <rect x="262" y="1118" width="72" height="32" fill="#0f172a"/>
  <text x="410" y="1015" font-family="Arial, sans-serif" font-size="30" font-weight="800" fill="$text">Scan or open the link</text>
  <text x="410" y="1070" font-family="Arial, sans-serif" font-size="25" fill="$muted">$shareUrl</text>
  <text x="410" y="1130" font-family="Arial, sans-serif" font-size="25" fill="$muted">Country: $country</text>
  <text x="126" y="1234" font-family="Arial, sans-serif" font-size="21" fill="$muted">Rewards are based on app participation and backend-verified activity.</text>
</svg>
SVG;
    }

    private function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
