# Plugin GLPI — Central de Avisos

Exibe avisos em formato de modal para usuários autenticados no GLPI,
independentemente da interface utilizada. A versão 2 transforma o aviso inicial
em uma central gerenciável e preserva a mensagem original como o primeiro aviso
criado após a atualização.

## Requisitos

- GLPI >= 11.0.0 e < 11.1.0 (inclui a versão 11.0.1)

## Instalação ou atualização

1. Copie a pasta `contractnotice` para a pasta `plugins` da instalação do GLPI.
   O resultado deve ser `<pasta-do-glpi>/plugins/contractnotice/`.
2. Acesse o GLPI com um usuário administrador.
3. Abra **Configuração > Plugins**.
4. Localize **Aviso de Contratos**. Na primeira instalação, clique em
   **Instalar** e depois em **Ativar**. Se o plugin já estiver instalado —
   inclusive na versão 1.0.0 ou 2.0.0 — clique em **Atualizar** após copiar
   os novos arquivos. Essa ação cria/atualiza as tabelas da central de avisos.
5. Entre novamente no GLPI com qualquer perfil para validar a mensagem.

Se o plugin não aparecer logo após a cópia, limpe o cache do GLPI:

```bash
php bin/console cache:clear
```

## Gerenciamento dos avisos

O menu **Administração > Disparar aviso** só é mostrado e aceito quando o perfil
ativo do usuário se chama exatamente **X GERENTE GLPI**. A regra é aplicada no
servidor, portanto acessar a URL diretamente não contorna a restrição.

Na central é possível criar, editar, ativar/desativar e apagar avisos. Cada
aviso permite escolher:

- público: todos os usuários, um ou mais grupos, ou um ou mais perfis;
- disparo: imediato ou sempre ao logar;
- início imediato ou programado, e encerramento opcional.

Avisos imediatos são verificados no carregamento da página e a cada 30 segundos;
por isso alcançam usuários que já estejam com o GLPI aberto. Avisos ao logar são
mostrados uma vez por sessão do GLPI enquanto estiverem no período definido.

Para evitar a mistura de modais, não é possível salvar dois avisos ativos ou
programados em períodos sobrepostos quando os públicos têm ao menos um usuário
em comum. Desative, altere o período ou ajuste o público do aviso existente.

## Desativação e remoção

Em **Configuração > Plugins**, desative o plugin. A desinstalação remove as duas
tabelas próprias do plugin e todos os avisos cadastrados.
