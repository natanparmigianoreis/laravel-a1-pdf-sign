<?php

namespace NatanParmigiano\LaravelA1PdfSign\Sign;

use Illuminate\Support\{Arr, Facades\File, Str};
use Doctrine\Common\Cache\Psr6\InvalidArgument;
use NatanParmigiano\LaravelA1PdfSign\Entities\ValidatedSignedPDF;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\{FileNotFoundException,
    HasNoSignatureOrInvalidPkcs7Exception,
    InvalidPdfFileException,
    ProcessRunTimeException
};
use Throwable;
use function PHPUnit\Framework\isEmpty;
use function PHPUnit\Framework\isNull;

class ValidatePdfSignature
{
    private string $pdfPath, $plainTextContent, $pkcs7Path, $CAChainPath = '';

    /**
     * @throws Throwable
     */
    public static function from(string $pdfPath, string $CAChainPath = null): ValidatedSignedPDF
    {
        if (!$CAChainPath) {
            $CAChainPathLocalVar = resource_path('certificates/default.crt');
        } else {
            $CAChainPathLocalVar = $CAChainPath;
        }

        return (new static)->setPdfPath($pdfPath)
            ->setCAChain($CAChainPathLocalVar)
            ->extractSignatureData()
            ->convertSignatureDataToPlainText()
            ->convertPlainTextToObject();
    }

    /**
     * @throws FileNotFoundException
     * @throws InvalidPdfFileException
     */
    private function setPdfPath(string $pdfPath): self
    {
        if (!Str::of($pdfPath)->lower()->endsWith('.pdf')) {
            throw new InvalidPdfFileException($pdfPath);
        }

        if (!File::exists($pdfPath)) {
            throw new FileNotFoundException($pdfPath);
        }

        $this->pdfPath = $pdfPath;

        return $this;
    }
    /**
     * @throws FileNotFoundException
     * @throws InvalidPdfFileException
     */
    private function setCAChain(string $CAChainPath): self
    {
        if (!Str::of($CAChainPath)->lower()->endsWith('.crt')) {
            throw new InvalidArgument($CAChainPath);
        }

        if (!File::exists($CAChainPath)) {
            throw new FileNotFoundException($CAChainPath);
        }

        $this->CAChainPath = $CAChainPath;

        return $this;
    }

    /**
     * @throws HasNoSignatureOrInvalidPkcs7Exception
     */
    private function extractSignatureData(): self
    {
        $content = File::get($this->pdfPath);
        $regexp  = '#ByteRange\[\s*(\d+) (\d+) (\d+)#'; // subexpressions are used to extract b and c
        $result  = [];
        preg_match_all($regexp, $content, $result);

        // $result[2][0] and $result[3][0] are b and c
        if (!isset($result[2][0]) && !isset($result[3][0])) {
            throw new HasNoSignatureOrInvalidPkcs7Exception($this->pdfPath);
        }

        $start = $result[2][0];
        $end   = $result[3][0];

        if ($stream = fopen($this->pdfPath, 'rb')) {
            $signature = stream_get_contents($stream, $end - $start - 2, $start + 1); // because we need to exclude < and > from start and end
            fclose($stream);
            $this->pkcs7Path = a1TempDir(tempFile: true, fileExt: '.pkcs7');
            File::put($this->pkcs7Path, hex2bin($signature));
        }

        return $this;
    }

    /**
     * @throws FileNotFoundException
     * @throws HasNoSignatureOrInvalidPkcs7Exception
     * @throws ProcessRunTimeException
     */
    private function convertSignatureDataToPlainText(): self
    {
        if (!$this->pkcs7Path) {
            throw new HasNoSignatureOrInvalidPkcs7Exception($this->pdfPath);
        }

        $output         = a1TempDir(tempFile: true, fileExt: '.txt');
        $openSslCommand = "openssl pkcs7 -in {$this->pkcs7Path} -inform DER -print_certs > {$output}";

        runCliCommandProcesses($openSslCommand);

        if (!File::exists($output)) {
            throw new FileNotFoundException($output);
        }

        $this->plainTextContent = File::get($output);

        File::delete([$output, $this->pkcs7Path]);

        return $this;
    }

    private function convertPlainTextToObject(): ValidatedSignedPDF
    {
        $finalContent = [];

        $certificateContent      = $this->plainTextContent;
        $certificate = openssl_x509_read($certificateContent);
        $parsedCertificate = openssl_x509_parse($certificate);
        $certificateFingerprint = openssl_x509_fingerprint($certificate);

        $trustedChainFilePath = $this->CAChainPath;

        $certificateFile  = a1TempDir(tempFile: true, fileExt: '.crt');
        $resultFile  = a1TempDir(tempFile: true, fileExt: '.txt');

        File::put($certificateFile, $certificateContent);

        $openSslCommand = "openssl verify -verbose -CAfile $trustedChainFilePath $certificateFile > $resultFile";

        $finalContent['validated'] = true;

        try {
            runCliCommandProcesses($openSslCommand);
            $verificationResult = File::get($resultFile);
        } catch (ProcessRunTimeException $e) {
            $finalContent['validated'] = false;
            $verificationResult = $e->getMessage();
        }

        if (!File::exists($resultFile)) {
            throw new FileNotFoundException($resultFile);
        }

        $finalContent['data'] = [
            'subject' => $parsedCertificate['subject'],
            'issuer' => $parsedCertificate['issuer'],
            'purposes' => $parsedCertificate['purposes'],
            'validFrom' => $parsedCertificate['validFrom_time_t'],
            'validTo' => $parsedCertificate['validTo_time_t'],
            'hash' => $parsedCertificate['hash'],
            'fingerprint' => $certificateFingerprint,
            'verificationResult' => $verificationResult
        ];

        return new ValidatedSignedPDF(
            $finalContent['validated'],
            $finalContent['data']
        );
    }
}
