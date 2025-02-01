<?php

declare(strict_types=1);

/**
 * LibreDTE: Biblioteca PHP (Núcleo).
 * Copyright (C) LibreDTE <https://www.libredte.cl>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General Affero de GNU publicada por
 * la Fundación para el Software Libre, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero SIN
 * GARANTÍA ALGUNA; ni siquiera la garantía implícita MERCANTIL o de APTITUD
 * PARA UN PROPÓSITO DETERMINADO. Consulte los detalles de la Licencia Pública
 * General Affero de GNU para obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de
 * GNU junto a este programa.
 *
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace libredte\lib\Core\Package\Billing\Component\Integration\Worker\SiiLazy\Job;

use Derafu\Lib\Core\Foundation\Abstract\AbstractJob;
use Derafu\Lib\Core\Foundation\Contract\JobInterface;
use Derafu\Lib\Core\Helper\Date;
use Derafu\Lib\Core\Helper\Rut;
use Derafu\Lib\Core\Package\Prime\Component\Xml\Contract\XmlComponentInterface;
use libredte\lib\Core\Package\Billing\Component\Integration\Contract\SiiRequestInterface;
use libredte\lib\Core\Package\Billing\Component\Integration\Exception\SiiConsumeWebserviceException;
use libredte\lib\Core\Package\Billing\Component\Integration\Exception\SiiValidateDocumentSignatureException;
use libredte\lib\Core\Package\Billing\Component\Integration\Support\Response\SiiValidateDocumentSignatureResponse;

/**
 * Clase para el envío de documentos al SII.
 *
 * Principalmente es para el envío y consulta de estado del envío de documentos
 * tributarios electrónicos en formato XML.
 */
class ValidateDocumentSignatureJob extends AbstractJob implements JobInterface
{
    public function __construct(
        private AuthenticateJob $authenticateJob,
        private ConsumeWebserviceJob $consumeWebserviceJob,
        private XmlComponentInterface $xmlComponent
    ) {
    }

    /**
     * Obtiene el estado avanzado de un documento en el SII.
     *
     * Este estado solo se obtiene si el documento se encuentra aceptado por el
     * SII, ya sea aceptado 100% OK o con reparos.
     *
     * Este servicio valida que el documento exista en SII (esté aceptado), que
     * los datos del documento proporcionados coincidan. Finalmente, valida que
     * la firma electrónica del documento coincida con la enviada al SII.
     *
     * Referencia: https://www.sii.cl/factura_electronica/factura_mercado/OIFE2006_QueryEstDteAv_MDE.pdf
     *
     * @param SiiRequestInterface $request Datos de la solicitud al SII.
     * @param string $company RUT de la empresa emisora del documento.
     * @param int $document Tipo de documento tributario electrónico.
     * @param int $number Folio del documento.
     * @param string $date Fecha de emisión del documento, formato: AAAA-MM-DD.
     * @param int $total Total del documento.
     * @param string $recipient RUT del receptor del documento.
     * @param string $signature Tag DTE/Signature/SignatureValue del XML.
     * @return SiiValidateDocumentSignatureResponse
     * @throws SiiValidateDocumentSignatureException En caso de error.
     */
    public function validate(
        SiiRequestInterface $request,
        string $company,
        int $document,
        int $number,
        string $date,
        int $total,
        string $recipient,
        string $signature
    ): SiiValidateDocumentSignatureResponse {
        // Validar los RUT que se utilizarán para la consulta de estado del DTE.
        Rut::validate($company);
        Rut::validate($recipient);
        [$rutCompany, $dvCompany] = Rut::toArray($company);
        [$rutRecipient, $dvRecipient] = Rut::toArray($recipient);

        // Validar fecha y convertir al formato del SII.
        $dateSii = Date::validateAndConvert($date, 'dmY');
        if ($dateSii === null) {
            throw new SiiValidateDocumentSignatureException(sprintf(
                'La fecha %s del documento no es válida, debe tener formato AAAA-MM-DD.',
                $date
            ));
        }

        // Obtener el token asociado al certificado digital.
        $token = $this->authenticateJob->authenticate($request);

        // Datos para la consulta.
        $requestData = [
            'RutEmpresa' => $rutCompany,
            'DvEmpresa' => $dvCompany,
            'RutReceptor' => $rutRecipient,
            'DvReceptor' => $dvRecipient,
            'TipoDte' => $document,
            'FolioDte' => $number,
            'FechaEmisionDte' => $dateSii,
            'MontoDte' => $total,
            'FirmaDte' => $signature,
            'Token' => $token,
        ];

        // Consultar el estado del documento, incluyendo su firma, al SII.
        try {
            $xmlResponse = $this->consumeWebserviceJob->sendRequest(
                $request,
                'QueryEstDteAv',
                'getEstDteAv',
                $requestData
            );
        } catch (SiiConsumeWebserviceException $e) {
            throw new SiiValidateDocumentSignatureException(sprintf(
                'No fue posible obtener el estado de la firma del documento T%dF%d de %d-%s desde el SII. %s',
                $document,
                $number,
                $rutCompany,
                $dvCompany,
                $e->getMessage()
            ));
        }

        // Armar estado del XML enviado y retornar.
        $responseData = $this->xmlComponent->getDecoderWorker()->decode(
            $xmlResponse
        );
        return new SiiValidateDocumentSignatureResponse(
            $responseData,
            $requestData
        );
    }
}
