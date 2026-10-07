<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $verified ? 'E-mail confirmado' : 'Link inválido' }} — Flinker</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f4f5f7; color: #1f2933; }
        main { background: #fff; max-width: 420px; margin: 16px; padding: 40px 32px; border-radius: 12px;
               box-shadow: 0 4px 24px rgba(0, 0, 0, .08); text-align: center; }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { margin: 0 0 28px; line-height: 1.5; color: #52606d; }
        a { display: inline-block; padding: 12px 28px; border-radius: 8px; background: #1f2933; color: #fff;
            text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        @if ($verified)
            <h1>E-mail confirmado!</h1>
            <p>Sua conta no Flinker está confirmada. Você já pode voltar para o aplicativo.</p>
        @else
            <h1>Link inválido ou expirado</h1>
            <p>Não foi possível confirmar o seu e-mail com este link. Entre no Flinker e peça um novo e-mail de confirmação.</p>
        @endif
        <a href="{{ $frontendUrl }}">Abrir o Flinker</a>
    </main>
</body>
</html>
