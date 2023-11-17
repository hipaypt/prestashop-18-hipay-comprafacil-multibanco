{if $status == 'ok'}
    <p>Dados de pagamento multibanco:<br/></p>
    {include file='module:comprafacil/views/templates/hook/_partials/payment_infos.tpl'}
    <p>Esta informação também foi enviada por email</p>
{else}
    <p class="warning">Ocorreu um erro com a encomenda, por favor volte a tentar outra vez ou entre em contacto connosco.</p>
{/if}
