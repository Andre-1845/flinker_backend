<?php

namespace App\Domain\Match\Services;

/**
 * Espelha as regras de src/lib/chatFilter.ts no frontend. O frontend já bloqueia
 * no cliente (feedback instantâneo), mas isso sozinho não impede um cliente
 * modificado (ou uma chamada direta à API) de burlar o filtro — por isso a
 * mesma regra é aplicada aqui de novo antes de gravar a mensagem. Mantenha as
 * duas listas em sincronia se uma mudar.
 */
class ChatContentFilter
{
    /** @var string[] */
    private array $patterns = [
        // Telefone (formato BR)
        '/\b\d{2}\s?\d{4,5}[-\s]?\d{4}\b/',
        '/\(\d{2}\)\s?\d{4,5}[-\s]?\d{4}/',
        // E-mail
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        // Palavras-chave
        '/whatsapp/i',
        '/wpp/i',
        '/zap/i',
        '/por\s+fora/i',
        '/pix\s+direto/i',
        '/paga\s+direto/i',
        '/fora\s+da\s+plataforma/i',
        '/fora\s+do\s+app/i',
        '/meu\s+n[uú]mero/i',
        '/meu\s+tel/i',
        '/liga\s+pra\s+mim/i',
        '/me\s+chama\s+no/i',
    ];

    public function isBlocked(string $message): bool
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }

    public function warningMessage(): string
    {
        return 'Mensagem bloqueada: para sua segurança, não é permitido compartilhar dados de contato ou negociar pagamentos fora da plataforma.';
    }
}
