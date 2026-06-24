<?php
namespace JDH\POS\Auth;
use PDO, Exception;
class TwoFactorAuth {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function generateSecret(): string { return $this->base32Encode(random_bytes(20)); }
    public function getQrUri(string $secret, string $label, string $issuer='JDH POS'): string { return 'otpauth://totp/'.rawurlencode($issuer.':'.$label).'?secret='.$secret.'&issuer='.rawurlencode($issuer); }
    public function verifyCode(string $secret, string $code): bool { return $this->getCode($secret) === $code; }
    public function enable(int $userId, string $type, string $secret, string $code): bool { if (!$this->verifyCode($secret,$code)) return false; $table=$type==='tenant'?'users':'admins'; $this->pdo->prepare("UPDATE {$table} SET two_factor_secret=?, two_factor_enabled=1, updated_at=NOW() WHERE id=?")->execute([$secret,$userId]); return true; }
    public function disable(int $userId, string $type): void { $table=$type==='tenant'?'users':'admins'; $this->pdo->prepare("UPDATE {$table} SET two_factor_secret=NULL, two_factor_enabled=0, updated_at=NOW() WHERE id=?")->execute([$userId]); }
    private function getCode(string $secret, int $timeSlice=null): string { $timeSlice??=floor(time()/30); $secretKey=$this->base32Decode($secret); $time=pack('N*',0).pack('N*',$timeSlice); $time=substr($time,-8); $hmac=hash_hmac('sha1',$time,$secretKey,true); $offset=ord($hmac[19])&0xf; $code=((ord($hmac[$offset])&0x7f)<<24)|((ord($hmac[$offset+1])&0xff)<<16)|((ord($hmac[$offset+2])&0xff)<<8)|(ord($hmac[$offset+3])&0xff); $code=$code%1000000; return str_pad((string)$code,6,'0',STR_PAD_LEFT); }
    private function base32Encode(string $data): string { $map='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $out=''; $len=strlen($data); for($i=0;$i<$len;$i+=5){ $chunk=substr($data,$i,5); $bin=''; for($j=0;$j<strlen($chunk);$j++) $bin.=str_pad(decbin(ord($chunk[$j])),8,'0',STR_PAD_LEFT); $bin=str_pad($bin,40,'0',STR_PAD_RIGHT); for($k=0;$k<40;$k+=5) $out.=$map[bindec(substr($bin,$k,5))]; } return $out; }
    private function base32Decode(string $data): string { $map='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $out=''; $bin=''; for($i=0;$i<strlen($data);$i++) $bin.=str_pad(decbin(strpos($map,$data[$i])),5,'0',STR_PAD_LEFT); for($i=0;$i<strlen($bin)-7;$i+=8) $out.=chr(bindec(substr($bin,$i,8))); return $out; }
}
