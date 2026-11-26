<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Textos em português do Brasil.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_enablews'] = 'Ligar web services e REST';
$string['action_fix'] = 'Corrigir papel e serviço';
$string['action_generatekey'] = 'Gerar chave';
$string['action_newkey'] = 'Gerar nova chave';
$string['actions_heading'] = 'Ações';
$string['actions_help'] = 'Cada botão pede confirmação antes de mudar algo. "Corrigir papel e serviço" só acrescenta o que falta; não apaga nada.';
$string['confirm_enablews'] = 'Ligar os web services e o protocolo REST no site todo? Isso permite que sistemas autorizados, como o VCOSTAEDU, falem com o Moodle.';
$string['confirm_fix'] = 'Conferir e criar o que faltar: papel, permissões, usuário de serviço, atribuição do papel e serviço com as funções? Nada existente é apagado.';
$string['confirm_generatekey'] = 'Gerar a chave de acesso do VCOSTAEDU?';
$string['confirm_newkey'] = 'Atenção: gerar uma nova chave derruba a conexão em uso. A chave atual deixa de funcionar na hora, e o VCOSTAEDU fica sem falar com o Moodle (cadastros, matrículas, entrada dos alunos) até você colar a chave nova no VCOSTAEDU. Gerar mesmo assim?';
$string['connect'] = 'Conectar ao VCOSTAEDU';
$string['connect_intro'] = 'Esta página confere se o Moodle está pronto para conversar com o VCOSTAEDU, corrige o que faltar e mostra os dados que você cola no VCOSTAEDU.';
$string['copied'] = 'Copiado para a área de transferência';
$string['copy'] = 'Copiar';
$string['data_heading'] = 'Dados para colar no VCOSTAEDU';
$string['data_intro'] = 'No VCOSTAEDU, na configuração do Moodle, cole estes dois dados.';
$string['data_token'] = 'Chave';
$string['data_token_none'] = 'Ainda não há chave. Clique em "Gerar chave".';
$string['data_token_warning'] = 'A chave dá acesso aos dados de alunos e disciplinas. Não envie por e-mail nem mostre em reunião gravada.';
$string['data_url'] = 'Endereço do Moodle';
$string['diag_col_item'] = 'Item';
$string['diag_col_message'] = 'O que fazer';
$string['diag_col_status'] = 'Situação';
$string['diag_email'] = 'Envio de e-mail do Moodle';
$string['diag_email_nosendmail'] = 'Não há servidor de e-mail (SMTP) configurado e o servidor do Moodle não tem programa de envio (sendmail): nenhum e-mail sai. Efeito: toda enturmação de aluno feita pelo VCOSTAEDU numa disciplina que tem professor (a primeira, a suspensão, a reativação e até a repetição sem mudança) grava a mudança, mas o Moodle responde com o erro "Message was not sent.", porque tenta mandar ao aluno a mensagem de boas-vindas da disciplina. A mudança vale; o VCOSTAEDU trata essa resposta como aviso. Para evitar: configure o servidor de e-mail (SMTP) em Administração do site > Servidor > E-mail > Configuração de saída de e-mail, ou desligue a mensagem de boas-vindas em Administração do site > Plugins > Inscrições > Inscrições manuais.';
$string['diag_email_nosmtp'] = 'Não há servidor de e-mail (SMTP) configurado; o Moodle tenta enviar pelo próprio servidor, e esse envio pode falhar. Efeito: toda enturmação de aluno feita pelo VCOSTAEDU numa disciplina que tem professor (a primeira, a suspensão, a reativação e até a repetição sem mudança) grava a mudança, mas o Moodle responde com o erro "Message was not sent.", porque tenta mandar ao aluno a mensagem de boas-vindas da disciplina. A mudança vale; o VCOSTAEDU trata essa resposta como aviso. Para evitar: configure o servidor de e-mail (SMTP) em Administração do site > Servidor > E-mail > Configuração de saída de e-mail, ou desligue a mensagem de boas-vindas em Administração do site > Plugins > Inscrições > Inscrições manuais.';
$string['diag_email_off'] = 'O envio de e-mail está desligado neste Moodle (noemailever). O Moodle não manda nenhum e-mail, nem a mensagem de boas-vindas das disciplinas, e as enturmações respondem sem erro. Se os alunos devem receber e-mail, ligue o envio e configure o servidor de e-mail.';
$string['diag_email_ok'] = 'O Moodle envia e-mail pelo servidor {$a}.';
$string['diag_email_processor'] = 'A saída de mensagens por e-mail está desligada em Administração do site > Mensagens. O Moodle não manda as mensagens por e-mail, nem a de boas-vindas das disciplinas, e as enturmações respondem sem erro. Ligue a saída por e-mail se os alunos devem receber e-mail.';
$string['diag_heading'] = 'Diagnóstico';
$string['diag_rest'] = 'Protocolo REST';
$string['diag_rest_error'] = 'Desligado. O VCOSTAEDU usa esse protocolo. Clique em "Ligar web services e REST".';
$string['diag_rest_ok'] = 'Ligado. Nada a fazer.';
$string['diag_role'] = 'Papel e permissões';
$string['diag_role_incomplete'] = 'Faltam permissões: {$a->missing}. Faltam papéis que ele pode atribuir: {$a->assign}. Clique em "Corrigir papel e serviço".';
$string['diag_role_missing'] = 'O papel "{$a}" não existe. Clique em "Corrigir papel e serviço".';
$string['diag_role_ok'] = 'O papel tem as {$a} permissões e pode atribuir estudante, professor e professor sem edição.';
$string['diag_role_unknown'] = 'Estas permissões não existem nesta versão do Moodle e foram ignoradas: {$a}. Avise a equipe do VCOSTAEDU.';
$string['diag_roleassign'] = 'Papel do usuário de serviço';
$string['diag_roleassign_error'] = 'O usuário de serviço não tem o papel. Clique em "Corrigir papel e serviço".';
$string['diag_roleassign_ok'] = 'O usuário de serviço tem o papel no site todo.';
$string['diag_service'] = 'Serviço e funções';
$string['diag_service_conflict'] = 'Outro serviço externo já usa o nome "{$a->name}" (nome curto: {$a->othershortname}), por isso o serviço do plugin não pôde ser criado. Renomeie ou remova o outro serviço em Administração do site > Servidor > Web services > Serviços externos e clique em "Corrigir papel e serviço".';
$string['diag_service_disabled'] = 'O serviço "{$a}" está desligado. Clique em "Corrigir papel e serviço".';
$string['diag_service_incomplete'] = 'Faltam funções no serviço "{$a->name}": {$a->list}. Clique em "Corrigir papel e serviço".';
$string['diag_service_missing'] = 'O serviço do plugin não existe. Clique em "Corrigir papel e serviço".';
$string['diag_service_ok'] = 'O serviço "{$a->name}" está ligado e tem as {$a->count} funções.';
$string['diag_service_unknown'] = 'Estas funções do serviço "{$a->name}" não existem nesta versão do Moodle: {$a->list}. Avise a equipe do VCOSTAEDU.';
$string['diag_serviceuser'] = 'Autorização no serviço';
$string['diag_serviceuser_error'] = 'O usuário de serviço não está autorizado no serviço. Clique em "Corrigir papel e serviço".';
$string['diag_serviceuser_ok'] = 'O usuário de serviço está autorizado a usar o serviço.';
$string['diag_token'] = 'Chave de acesso';
$string['diag_token_missing'] = 'Ainda não há chave. Clique em "Gerar chave".';
$string['diag_token_ok'] = 'A chave existe. Ela aparece abaixo, em "Dados para colar no VCOSTAEDU".';
$string['diag_user'] = 'Usuário de serviço';
$string['diag_user_missing'] = 'O usuário "{$a}" não existe. Clique em "Corrigir papel e serviço".';
$string['diag_user_ok'] = 'O usuário "{$a}" existe e está ativo.';
$string['diag_user_suspended'] = 'O usuário "{$a}" está suspenso ou bloqueado para entrar. Reative-o em Administração do site > Usuários. O plugin não reativa sozinho.';
$string['diag_versions'] = 'Versões';
$string['diag_versions_ok'] = 'Moodle {$a->moodle}, plugin {$a->plugin}. Versão do Moodle testada com o plugin.';
$string['diag_versions_untested'] = 'Moodle {$a->moodle}, plugin {$a->plugin}. O plugin foi testado do Moodle 4.5 ao 5.3. Confira o diagnóstico com cuidado.';
$string['diag_webservices'] = 'Web services';
$string['diag_webservices_error'] = 'Desligados. O VCOSTAEDU não consegue falar com o Moodle. Clique em "Ligar web services e REST".';
$string['diag_webservices_ok'] = 'Ligados. Nada a fazer.';
$string['done_enablews'] = 'Web services e REST ligados.';
$string['done_fix'] = 'Papel e serviço conferidos e corrigidos.';
$string['done_generatekey'] = 'Chave gerada. Copie e cole no VCOSTAEDU.';
$string['done_generatekey_exists'] = 'Já existe uma chave. Para trocar, use "Gerar nova chave".';
$string['done_newkey'] = 'Nova chave gerada. A anterior não funciona mais. Copie e cole a nova no VCOSTAEDU.';
$string['envservicename'] = 'Nome do serviço VCOSTAEDU livre';
$string['event_login_link_created'] = 'Link de entrada gerado';
$string['event_login_link_refused'] = 'Entrada por link recusada';
$string['event_login_link_used'] = 'Entrada por link feita';
$string['loginpage'] = 'Entrada pelo VCOSTAEDU';
$string['loginvalidity'] = 'Validade do link de entrada (segundos)';
$string['loginvalidity_desc'] = 'Por quanto tempo um link de entrada enviado pelo VCOSTAEDU pode ser aberto. Depois disso ele não funciona mais. Entre {$a->min} e {$a->max} segundos.';
$string['loginvalidity_error'] = 'Digite um número inteiro entre {$a->min} e {$a->max}.';
$string['pluginname'] = 'VCOSTAEDU';
$string['privacy:metadata:core_userkey'] = 'Os links de entrada de uso único ficam guardados como chaves de usuário por alguns segundos e são apagados quando usados ou vencidos.';
$string['privacy:metadata:vcostaedu'] = 'O VCOSTAEDU é a plataforma acadêmica externa que este plugin conecta ao Moodle. Com a chave gerada na página "Conectar ao VCOSTAEDU", ele lê e grava, pelos web services do Moodle, os dados abaixo, e pede links de entrada para os alunos.';
$string['privacy:metadata:vcostaedu:courses'] = 'As disciplinas e categorias em que a pessoa está.';
$string['privacy:metadata:vcostaedu:email'] = 'O endereço de e-mail da pessoa.';
$string['privacy:metadata:vcostaedu:enrolments'] = 'As matrículas e os papéis da pessoa nas disciplinas, e se estão suspensas.';
$string['privacy:metadata:vcostaedu:fullname'] = 'O nome e o sobrenome da pessoa.';
$string['privacy:metadata:vcostaedu:grades'] = 'As notas da pessoa nas disciplinas.';
$string['privacy:metadata:vcostaedu:idnumber'] = 'O número de identificação da pessoa.';
$string['privacy:metadata:vcostaedu:loginurl'] = 'Os links de entrada de uso único pedidos para a pessoa.';
$string['privacy:metadata:vcostaedu:suspended'] = 'Se a conta da pessoa está suspensa.';
$string['privacy:metadata:vcostaedu:username'] = 'O nome de usuário da pessoa no Moodle.';
$string['refused_authdisabled'] = 'O método de entrada desta conta está desligado no Moodle.';
$string['refused_busy'] = 'Este link está sendo aberto em outra aba ou janela.';
$string['refused_expiredkey'] = 'Este link de entrada venceu.';
$string['refused_gotologin'] = 'Ir para a página de entrada do Moodle';
$string['refused_help'] = 'Nenhuma sessão foi aberta. Volte ao VCOSTAEDU e clique de novo, ou entre no Moodle com seu usuário e senha.';
$string['refused_invalidkey'] = 'Este link de entrada não vale: ele já foi usado ou não existe.';
$string['refused_loginfailed'] = 'Não foi possível concluir a entrada.';
$string['refused_userdeleted'] = 'A conta deste link foi excluída do Moodle.';
$string['refused_userisadmin'] = 'Contas de administrador do site não entram por link. Entre com usuário e senha.';
$string['refused_userisguest'] = 'A conta de visitante não entra por link.';
$string['refused_userisservice'] = 'A conta de serviço do VCOSTAEDU não entra por link.';
$string['refused_usernologin'] = 'Esta conta está bloqueada para entrar no Moodle.';
$string['refused_usernotconfirmed'] = 'Esta conta ainda não foi confirmada no Moodle.';
$string['refused_usernotfound'] = 'A conta deste link não existe mais no Moodle.';
$string['refused_usersuspended'] = 'Esta conta está suspensa no Moodle. Procure a secretaria.';
$string['servicemissing'] = 'O serviço VCOSTAEDU não existe. Use "Corrigir papel e serviço".';
$string['servicenameconflict'] = 'Já existe neste Moodle outro serviço externo com o nome "{$a->name}" ou o nome curto "{$a->shortname}" (nome curto do outro: {$a->othershortname}). O plugin precisa desse nome e não instala enquanto ele estiver em uso. Renomeie ou remova o outro serviço em Administração do site > Servidor > Web services > Serviços externos e tente de novo.';
$string['serviceroledescription'] = 'Papel usado pela integração com o VCOSTAEDU. Criado pelo plugin local_vcostaedu.';
$string['servicerolename'] = 'VCOSTAEDU (serviço)';
$string['serviceuserdescription'] = 'Conta de serviço da integração com o VCOSTAEDU. Não use para entrar no Moodle.';
$string['serviceuserlastname'] = 'Serviço';
$string['settings_link'] = 'Validade do link de entrada: {$a} segundos. Alterar nas configurações.';
$string['settingspage'] = 'Configurações';
$string['status_error'] = 'Erro';
$string['status_ok'] = 'Certo';
$string['status_warning'] = 'Atenção';
$string['step_button'] = '{$a->number}. {$a->label}';
$string['step_enablews'] = 'Ligue os web services e o protocolo REST.';
$string['step_fix'] = 'Corrija o papel e o serviço.';
$string['step_generatekey'] = 'Gere a chave e copie o endereço e a chave para o VCOSTAEDU.';
$string['steps_intro'] = 'Faltam {$a} passos. Faça nesta ordem:';
$string['vcostaedu:requestloginurl'] = 'Pedir link de entrada para um usuário';
$string['vcostaedu_authdisabled'] = 'O método de autenticação do usuário "{$a}" está desligado no Moodle. Nenhum link foi gerado.';
$string['vcostaedu_userdeleted'] = 'O usuário "{$a}" foi excluído. Nenhum link foi gerado.';
$string['vcostaedu_userisadmin'] = '"{$a}" é administrador do site. Administrador nunca entra por link. Nenhum link foi gerado.';
$string['vcostaedu_userisguest'] = '"{$a}" é a conta de visitante, que não entra por link. Nenhum link foi gerado.';
$string['vcostaedu_userisservice'] = '"{$a}" é a conta de serviço do VCOSTAEDU, que não entra por link. Nenhum link foi gerado.';
$string['vcostaedu_usernologin'] = 'O usuário "{$a}" está com a autenticação "Sem login" (nologin). Nenhum link foi gerado.';
$string['vcostaedu_usernotconfirmed'] = 'O usuário "{$a}" ainda não foi confirmado. Nenhum link foi gerado.';
$string['vcostaedu_usernotfound'] = 'Não existe usuário "{$a}" neste Moodle. Nenhum link foi gerado.';
$string['vcostaedu_usersuspended'] = 'O usuário "{$a}" está suspenso. Nenhum link foi gerado.';
