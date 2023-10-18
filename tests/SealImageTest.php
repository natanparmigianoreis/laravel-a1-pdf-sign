<?php

namespace NatanParmigiano\LaravelA1PdfSign\Tests;

use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManager as IMG;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\CertificateOutputNotFoundException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\FileNotFoundException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\InvalidCertificateContentException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\InvalidPdfSignModeTypeException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\InvalidPFXException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\Invalidx509PrivateKeyException;
use NatanParmigiano\LaravelA1PdfSign\Exceptions\ProcessRunTimeException;
use NatanParmigiano\LaravelA1PdfSign\Sign\ManagedCertificate;
use NatanParmigiano\LaravelA1PdfSign\Sign\SealImage;
use NatanParmigiano\LaravelA1PdfSign\Sign\PDFSigner;
use Throwable;

class SealImageTest extends TestCase
{
    /**
     * @throws FileNotFoundException
     * @throws InvalidCertificateContentException
     * @throws CertificateOutputNotFoundException
     * @throws InvalidPFXException
     * @throws ProcessRunTimeException
     * @throws Invalidx509PrivateKeyException
     * @throw \Intervention\Image\ImageManager\Exception\NotReadableException
     */
    public function testGenerateImageFromCertFile()
    {
        $cert = new ManagedCertificate;
        $cert->makeDebugCertificate();

        $image = SealImage::fromCert($cert);

        $interventionImg = new IMG(['driver' => SealImage::IMAGE_DRIVER_GD]);
        $interventionImg = $interventionImg->make($image);

        $this->assertEqualsIgnoringCase('image/png', $interventionImg->mime());
        $this->assertEquals(590, $interventionImg->width());
        $this->assertEquals(295, $interventionImg->height());
    }

    /**
     * @throws CertificateOutputNotFoundException
     * @throws FileNotFoundException
     * @throws InvalidCertificateContentException
     * @throws InvalidPFXException
     * @throws Invalidx509PrivateKeyException
     * @throws ProcessRunTimeException
     * @throws InvalidPdfSignModeTypeException
     * @throws Throwable
     * @throw \Intervention\Image\ImageManager\Exception\NotReadableException
     */
    public function testInsertSealImageOnPdfFile()
    {
        $cert = new ManagedCertificate;
        $cert->makeDebugCertificate();

        $image = SealImage::fromCert($cert);
        $imagePath = a1TempDir(true, '.png');
        File::put($imagePath, $image);
        $this->assertTrue(File::exists($imagePath));

        $pdfPath = a1TempDir(true, '.pdf');
        try {
            $pdf = new PDFSigner(__DIR__ . '/Resources/test.pdf', $cert);
            $resource = $pdf->setImage($imagePath)
                            ->sign();
            File::put($pdfPath, $resource);
        } catch (Throwable $e) {
            throw new $e;
        }

        $this->assertTrue(File::exists($pdfPath));

        $validation = validatePdfSignature($pdfPath);
        $this->assertTrue($validation->isValidated);

        File::delete([$imagePath, $pdfPath]);
    }
}
