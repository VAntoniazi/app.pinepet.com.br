<?php
$messages=[
  400=>['Requisição inválida','Revise os dados enviados e tente novamente.'],401=>['Sessão necessária','Entre novamente para continuar.'],
  403=>['Acesso recusado','Sua conta não possui permissão para acessar este conteúdo.'],404=>['Página não encontrada','O endereço informado não existe ou foi removido.'],
  405=>['Ação não permitida','Este endereço não aceita o método utilizado.'],413=>['Conteúdo muito grande','A requisição ultrapassou o limite permitido.'],
  419=>['Sessão expirada','Atualize a página e envie o formulário novamente.'],422=>['Dados inválidos','Revise os campos informados.'],
  429=>['Muitas tentativas','Aguarde um pouco antes de tentar novamente.'],500=>['Tivemos uma instabilidade','Tente novamente em alguns instantes.'],
];[$heading,$description]=$messages[$status]??['Não foi possível continuar','Tente novamente.'];if(!empty($customMessage)&&$status<500)$description=$customMessage;
?>
<section class="error-page"><a class="brand" href="/"><img src="<?=e(asset('/assets/images/logo.svg'))?>" alt=""><span><b>Pine</b>Pet</span></a><strong><?=e($status)?></strong><h1><?=e($heading)?></h1><p><?=e($description)?></p><a class="primary-button" href="/">Ir para o início</a></section>
