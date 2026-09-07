<?php

namespace Ramblers\Component\Ra_setup\Site\Service;

defined('_JEXEC') or die;

class SenderRegistrationValidator
{
    public const SINGLE_EMAIL = 'email';
    public const SENDER_DOMAIN = 'domain';
    public const BOTH = 'both';

    public function validate(string $choice, string $senderEmail, string $website): array
    {
        if (!in_array($choice, [self::SINGLE_EMAIL, self::SENDER_DOMAIN, self::BOTH], true)) {
            throw new \InvalidArgumentException('Select how the SMTP2GO sender is to be verified.');
        }

        $senderEmail = trim($senderEmail);
        $senderDomain = $this->getSenderEmailDomain($senderEmail);
        $hostname = null;

        if ($choice !== self::SINGLE_EMAIL) {
            $hostname = $this->getWebsiteHostname($website);

            if (!hash_equals($hostname, $senderDomain)) {
                throw new \InvalidArgumentException(
                    'RA Delivery sender email "' . $senderEmail . '" uses domain "' . $senderDomain
                        . '", which does not match the sender domain "' . $hostname
                        . '" derived from com_ra_tools.website. Update sender_email before continuing.'
                );
            }
        }

        return [
            'choice' => $choice,
            'sender_email' => $senderEmail,
            'sender_domain' => $hostname,
        ];
    }

    public function getInstructions(array $registration, bool $completed): string
    {
        $messages = [];

        if (in_array($registration['choice'], [self::SINGLE_EMAIL, self::BOTH], true)) {
            $messages[] = $completed
                ? 'SMTP2GO has sent a verification message to "' . $registration['sender_email']
                    . '"; follow the link in that message to verify it as a single sender email.'
                : 'After Step 8, SMTP2GO will send a verification message to "'
                    . $registration['sender_email']
                    . '"; follow the link in that message to verify it as a single sender email.';
        }

        if (in_array($registration['choice'], [self::SENDER_DOMAIN, self::BOTH], true)) {
            $messages[] = $completed
                ? 'The CNAME records for sender domain "' . $registration['sender_domain']
                    . '" must now be updated.'
                : 'After Step 8, update the CNAME records for sender domain "'
                    . $registration['sender_domain'] . '" using the returned SMTP2GO details.';
        }

        return implode(' ', $messages);
    }

    private function getWebsiteHostname(string $website): string
    {
        $website = trim($website);
        $parts = parse_url($website);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $hostname = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (!in_array($scheme, ['http', 'https'], true)
            || $hostname === ''
            || filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            throw new \InvalidArgumentException(
                'RA Tools website "' . $website . '" is not a valid HTTP or HTTPS website URL.'
            );
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            throw new \InvalidArgumentException(
                'RA Tools website hostname "' . $hostname . '" is an IP address. '
                    . 'SMTP2GO sender-domain registration requires a public DNS hostname; '
                    . 'update com_ra_tools.website before continuing.'
            );
        }

        if (!str_contains($hostname, '.')
            || preg_match('/(?:^|\.)(?:localhost|local|test|invalid|example)$/i', $hostname)
        ) {
            throw new \InvalidArgumentException(
                'RA Tools website hostname "' . $hostname . '" is not a public DNS hostname. '
                    . 'SMTP2GO requires the production sender domain before continuing.'
            );
        }

        return (string) preg_replace('/^www\./i', '', $hostname);
    }

    private function getSenderEmailDomain(string $senderEmail): string
    {
        if (filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(
                'RA Delivery sender_email "' . $senderEmail
                    . '" is not a valid email address. Update it before continuing.'
            );
        }

        $separator = strrpos($senderEmail, '@');
        $domain = strtolower(rtrim(substr($senderEmail, $separator + 1), '.'));

        if ($domain === ''
            || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
        ) {
            throw new \InvalidArgumentException(
                'RA Delivery sender_email "' . $senderEmail
                    . '" does not contain a valid DNS domain.'
            );
        }

        return $domain;
    }
}
