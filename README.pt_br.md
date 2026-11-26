# Conexão do Moodle com o VCOSTAEDU (local_vcostaedu)

English: [README.md](README.md).

## Descrição

Este plugin local conecta o Moodle ao VCOSTAEDU, uma plataforma acadêmica gratuita e de
código aberto que trabalha junto com o Moodle. Ele faz duas coisas:

1. **Prepara o Moodle pela tela.** A página *Administração do site > Plugins > Plugins
   locais > VCOSTAEDU > Conectar ao VCOSTAEDU* confere, item por item, o que a integração
   precisa (web services, REST, usuário e papel de serviço, o serviço externo, uma chave,
   envio de e-mail) e corrige o que falta com poucos botões. Depois mostra os dois dados que
   você cola no VCOSTAEDU: o endereço do Moodle e a chave.
2. **Entrada do aluno por link de uso único.** O VCOSTAEDU pede ao Moodle um link curto, de
   uso único, e o aluno entra sem digitar senha. Nunca há link, nem sessão, para conta
   suspensa, excluída, não confirmada ou bloqueada, visitante ou administrador do site.

O plugin usa só a API padrão de web services do Moodle. Não tem dependência e não chama
nenhum servidor.

## Requisitos

- Moodle 4.5 a 5.3 (testado no 4.5.16, 5.0.12, 5.1.9, 5.2.5 e 5.3.1), com MySQL/MariaDB ou
  PostgreSQL, e a versão do PHP que aquela versão do Moodle pede.
- Nenhum outro plugin e nenhuma ferramenta de linha de comando.
- Os alunos precisam de um método de autenticação ligado no site; o VCOSTAEDU os cria com
  contas manuais.

## Instalação

Instale o zip em *Administração do site > Plugins > Instalar plugins*, ou copie a pasta
`vcostaedu` à mão e rode a atualização. O destino depende da versão do Moodle:
`local/vcostaedu` no Moodle 4.5 e 5.0, `public/local/vcostaedu` no Moodle 5.1 ou mais novo.
Depois abra *Conectar ao VCOSTAEDU* e siga o diagnóstico.

Há dois pacotes: `local_vcostaedu-<versão>.zip` (só inglês, o do Moodle Marketplace) e
`local_vcostaedu-<versão>-ptbr.zip` (o mesmo, com os textos em português).

Guia passo a passo com telas: [docs/INSTALAR.md](docs/INSTALAR.md).

Antes de instalar, vale saber:

- A instalação cria o papel `vcostaedu_ws`, o usuário de serviço `vcostaedu_ws` com esse papel
  no site todo, e o serviço externo "VCOSTAEDU" (nome curto `local_vcostaedu`) com 17 funções.
  Papel ou usuário que já tenha esse nome é reaproveitado. Se outro serviço externo já usa o
  nome "VCOSTAEDU" ou o nome curto `local_vcostaedu`, a instalação para na tela de conferência
  do servidor e diz o que fazer.
- A instalação não liga os web services nem o REST; um botão da página de conexão faz isso,
  quando o administrador decidir.
- Desinstalar remove o serviço externo com as suas chaves, os links de entrada pendentes, e o
  papel e o usuário que o plugin criou; papel ou usuário de mesmo nome que já existia antes
  fica.

## Serviço externo

O VCOSTAEDU é uma plataforma acadêmica gratuita e de código aberto. Com a chave gerada na
página *Conectar ao VCOSTAEDU*, ele chama os web services do Moodle para criar e atualizar
usuários, categorias e disciplinas, enturmar e desenturmar alunos, ler matrículas e notas,
achar pelo e-mail contas que já existem (a função do plugin `local_vcostaedu_find_users_by_email`
devolve só os ids delas) e pedir links de entrada.

- O que depende do VCOSTAEDU: tudo o que o plugin prepara é usado pelo VCOSTAEDU. Sem ele, o
  plugin só prepara o Moodle; a função de link de entrada continua podendo ser chamada por
  qualquer sistema que tenha a chave.
- Credenciais: não há conta a criar em outro lugar. A chave é gerada pela própria página do
  plugin e colada no VCOSTAEDU.
- Testar sem o VCOSTAEDU: [docs/REVIEW.md](docs/REVIEW.md) mostra como fazer tudo com
  chamadas simples aos web services.

### E-mail e enturmações

O Moodle manda ao aluno a mensagem de boas-vindas da disciplina em toda chamada de
enturmação manual, inclusive ao suspender, reativar ou repetir a enturmação, quando a
disciplina tem professor. Se o Moodle não consegue enviar e-mail, cada uma dessas chamadas
grava a mudança e mesmo assim responde com o erro "Message was not sent.". O VCOSTAEDU trata
essa resposta como aviso. Para evitar, configure o envio de e-mail, ou desligue a mensagem de
boas-vindas em *Administração do site > Plugins > Inscrições > Inscrições manuais*. A página
de conexão avisa, e o plugin não muda essas configurações.

## Privacidade

O plugin não guarda dado pessoal próprio. As chaves de entrada de uso único ficam alguns
segundos na tabela de chaves de usuário do Moodle e são apagadas quando usadas ou vencidas.
O VCOSTAEDU, sistema externo, lê e grava dados de usuários, disciplinas, matrículas e notas
pelos web services; o provedor de privacidade declara isso, com cada tipo de dado.

## Segurança

As regras da entrada por link, cada uma com a sua prova, estão em
[docs/REVIEW.md](docs/REVIEW.md) (em inglês).

## Relato de erros

Relate erros em: `<endereço do rastreador de erros, a preencher quando o repositório público for criado>`.

## Documentação

- Guia de instalação: [docs/INSTALAR.md](docs/INSTALAR.md) (inglês: [docs/INSTALL.md](docs/INSTALL.md)).
- Guia do revisor: [docs/REVIEW.md](docs/REVIEW.md).
- Mudanças: [CHANGES.md](CHANGES.md).

## Licença

GNU GPL v3 ou posterior. Veja [LICENSE](LICENSE).

Copyright 2026 VCOSTA.
