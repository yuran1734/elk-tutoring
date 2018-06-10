<?php

namespace TecPro\MailBundle\Service;

require '../vendor/autoload.php';

use Doctrine\ORM\EntityManager;
use TecPro\MailBundle\Entity\Attachment;

class AttachmentService
{

    const S3_URL_PREFIX = 'https://venueattachments.s3.amazonaws.com/';
    private $s3Service;
    private $entityManager;

    public function __construct(S3Service $s3Service, EntityManager $entityManager)
    {
        $this->s3Service = $s3Service;
        $this->entityManager = $entityManager;
    }

    public function getAttachments($files)
    {
        if (is_null($files)) {
            return null;
        }
        $attachments = [];
        foreach ($files as $file) {
            array_push($attachments, $this->getAttachment($file));
        }
        return $attachments;
    }

    private function getAttachment($file)
    {
        $attachment = new Attachment();
        $attachment->setFile($file);
        $attachment->setS3ObjectUrl($this->s3Service->upload($file));
        $attachment->setDateCreated(new \DateTime());
        return $attachment;
    }

    public function setIsExpiredTrue($path)
    {
        $attachment = $this->findByPath(urldecode($path));
        $attachment->setIsRemovedFromS3Storage(true);
        $this->persist($attachment);
    }

    public function findByPath($path): Attachment
    {
        $url = $this->getFullUrl($path);
        return $this->findByUrl($url);
    }

    private function getFullUrl($path)
    {
        $pathEncoded = $this->encodeFileName($path);
        return self::S3_URL_PREFIX . $pathEncoded;
    }

    private function encodeFileName($path)
    {
        $pathParts = explode('/', $path);
        $fileName = array_pop($pathParts);
        $fileName = rawurlencode($fileName);
        array_push($pathParts, $fileName);
        return implode('/', $pathParts);
    }

    public function findByUrl($url): Attachment
    {
        return $this->entityManager->getRepository(Attachment::class)->findOneBy(array("s3ObjectUrl" => $url));
    }

    public function persist(Attachment $attachment)
    {
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();
    }

    public function removeById($id)
    {
        $attachment = $this->findAttachment($id);
        $this->removeFromDatabase($attachment);
        $this->s3Service->delete($attachment);
    }

    private function findAttachment($id)
    {
        return $this->entityManager->getRepository(Attachment::class)->find($id);
    }

    private function removeFromDatabase(Attachment $attachment)
    {
        $this->entityManager->remove($attachment);
        $this->entityManager->flush($attachment);
    }

}
