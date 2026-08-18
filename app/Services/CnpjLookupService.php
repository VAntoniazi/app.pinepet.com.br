<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\IntegrationRepository;

final class CnpjLookupService
{
    private const ENDPOINTS=['SERPRO_CNPJ_V2'=>['PRODUCAO'=>['token'=>'https://gateway.apiserpro.serpro.gov.br/token','query'=>'https://gateway.apiserpro.serpro.gov.br/consulta-cnpj-df/v2/empresa'],'HOMOLOGACAO'=>['token'=>'https://gateway.apiserpro.serpro.gov.br/token','query'=>'https://gateway.apiserpro.serpro.gov.br/consulta-cnpj-df-trial/v2/empresa']]];
    public function __construct(private readonly IntegrationRepository$integrations,private readonly IntegrationSecretService$secrets){}
    public function lookup(string$cnpj):array
    {
        $config=$this->integrations->active('CONSULTA_CNPJ');if(!$config)throw new \RuntimeException('A consulta oficial de CNPJ ainda nao foi configurada.');$urls=self::ENDPOINTS[$config['provedor']][$config['ambiente']]??null;if(!$urls)throw new \RuntimeException('Perfil de integracao CNPJ nao permitido.');$secret=$this->secrets->decrypt((string)$config['cliente_segredo_cifrado']);
        $token=$this->request($urls['token'],'POST',['Authorization: Basic '.base64_encode((string)$config['cliente_id'].':'.$secret),'Content-Type: application/x-www-form-urlencoded'],'grant_type=client_credentials');$access=$token['access_token']??null;if(!is_string($access)||$access==='')throw new \RuntimeException('A API de CNPJ nao retornou token de acesso.');$payload=$this->request($urls['query'].'/'.rawurlencode($cnpj),'GET',['Authorization: Bearer '.$access,'Accept: application/json','X-Request-Tag: pinepet-onboarding']);return$this->normalize($payload,$cnpj);
    }
    private function request(string$url,string$method,array$headers,?string$body=null):array
    {
        if(!function_exists('curl_init'))throw new \RuntimeException('A extensao cURL e obrigatoria para consulta CNPJ.');$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_MAXREDIRS=>0]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);if(!is_string($raw)||$status<200||$status>=300)throw new \RuntimeException($status===404?'CNPJ nao encontrado na base oficial.':'Falha temporaria na consulta oficial de CNPJ.');$data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($data))throw new \RuntimeException('Resposta invalida da API de CNPJ.');return$data;
    }
    private function normalize(array$p,string$cnpj):array
    {
        $address=is_array($p['endereco']??null)?$p['endereco']:[];$city=is_array($address['municipio']??null)?($address['municipio']['descricao']??null):($address['municipio']??$p['municipio']??null);$status=is_array($p['situacao_cadastral']??$p['situacaoCadastral']??null)?(($p['situacao_cadastral']??$p['situacaoCadastral'])['descricao']??($p['situacao_cadastral']??$p['situacaoCadastral'])['codigo']??null):null;$nature=$p['natureza_juridica']??$p['naturezaJuridica']??null;$cnae=$p['cnae_principal']??$p['cnaePrincipal']??[];
        return['cnpj'=>$cnpj,'razao_social'=>$this->text($p['nome_empresarial']??$p['nomeEmpresarial']??null,255),'nome_fantasia'=>$this->text($p['nome_fantasia']??$p['nomeFantasia']??null,255),'situacao'=>$this->text($status,80),'natureza_juridica'=>$this->text(is_array($nature)?($nature['descricao']??null):$nature,180),'data_abertura'=>$this->date($p['data_abertura']??$p['dataAbertura']??null),'cnae_codigo'=>$this->text(is_array($cnae)?($cnae['codigo']??null):null,12),'cnae_descricao'=>$this->text(is_array($cnae)?($cnae['descricao']??null):null,255),'cep'=>preg_replace('/\D/','',(string)($address['cep']??'')),'logradouro'=>$this->text($address['logradouro']??null,255),'numero'=>$this->text($address['numero']??null,30),'complemento'=>$this->text($address['complemento']??null,180),'bairro'=>$this->text($address['bairro']??null,120),'municipio'=>$this->text($city,120),'uf'=>$this->text($address['uf']??null,2),'email'=>$this->text($p['correio_eletronico']??$p['correioEletronico']??null,254),'telefone'=>$this->phone($p),'resposta_hash'=>hash('sha256',json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
    }
    private function phone(array$p):?string{$v=$p['telefones']??$p['telefone']??null;if(is_array($v)&&isset($v[0])){$v=$v[0];if(is_array($v))return preg_replace('/\D/','',(string)($v['ddd']??'').(string)($v['numero']??''));}return$this->text($v,30);}
    private function text(mixed$v,int$max):?string{if(!is_scalar($v))return null;$v=preg_replace('/\s+/u',' ',trim((string)$v));return$v===''?null:mb_substr($v,0,$max);}
    private function date(mixed$v):?string{if(!is_string($v)||$v==='')return null;foreach(['Y-m-d','d/m/Y']as$f){$d=\DateTimeImmutable::createFromFormat('!'.$f,$v);if($d&&$d->format($f)===$v)return$d->format('Y-m-d');}return null;}
}
