<?php
namespace App\Controller\Api\Stations\Streamers;

use App\Controller\Api\AbstractApiCrudController;
use App\Entity;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Radio\Filesystem;
use App\Utilities;
use Azura\Doctrine\Paginator;
use Psr\Http\Message\ResponseInterface;

class BroadcastsController extends AbstractApiCrudController
{
    protected string $entityClass = Entity\StationStreamerBroadcast::class;

    /**
     * @param ServerRequest $request
     * @param Response $response
     * @param string|int $station_id
     * @param int $id
     *
     * @return ResponseInterface
     */
    public function listAction(
        ServerRequest $request,
        Response $response,
        $station_id,
        $id
    ): ResponseInterface {
        $station = $request->getStation();
        $streamer = $this->getStreamer($station, $id);

        if (null === $streamer) {
            return $response->withStatus(404)
                ->withJson(new Entity\Api\Error(404, __('Record not found!')));
        }

        $query = $this->em->createQuery(/** @lang DQL */ 'SELECT ssb 
            FROM App\Entity\StationStreamerBroadcast ssb
            WHERE ssb.station = :station AND ssb.streamer = :streamer
            ORDER BY ssb.timestampStart DESC')
            ->setParameter('station', $station)
            ->setParameter('streamer', $streamer);

        $paginator = new Paginator($query);
        $paginator->setFromRequest($request);

        $is_bootgrid = $paginator->isFromBootgrid();
        $router = $request->getRouter();

        $paginator->setPostprocessor(function ($row) use ($is_bootgrid, $router) {
            /** @var Entity\StationStreamerBroadcast $row */
            $return = $this->_normalizeRecord($row);

            if (!empty($row->getRecordingPath())) {
                $return['links'] = [
                    'download' => $router->fromHere(
                        'api:stations:streamer:broadcast:download',
                        ['broadcast_id' => $row->getId()],
                        [],
                        true
                    ),
                    'delete' => $router->fromHere(
                        'api:stations:streamer:broadcast:delete',
                        ['broadcast_id' => $row->getId()],
                        [],
                        true
                    ),
                ];
            }

            if ($is_bootgrid) {
                return Utilities::flattenArray($return, '_');
            }

            return $return;
        });

        return $paginator->write($response);
    }

    /**
     * @param ServerRequest $request
     * @param Response $response
     * @param Filesystem $filesystem
     * @param string|int $station_id
     * @param int $id
     * @param int $broadcast_id
     *
     * @return ResponseInterface
     */
    public function downloadAction(
        ServerRequest $request,
        Response $response,
        Filesystem $filesystem,
        $station_id,
        $id,
        $broadcast_id
    ): ResponseInterface {
        $station = $request->getStation();
        $broadcast = $this->getRecord($station, $broadcast_id);

        if (null === $broadcast) {
            return $response->withStatus(404)
                ->withJson(new Entity\Api\Error(404, __('Record not found!')));
        }

        $recordingPath = $broadcast->getRecordingPath();

        if (empty($recordingPath)) {
            return $response->withStatus(400)
                ->withJson(new Entity\Api\Error(400, __('No recording available.')));
        }

        $fs = $filesystem->getForStation($station);
        $filename = basename($recordingPath);

        $recordingPath = 'recordings://' . $recordingPath;
        $fh = $fs->readStream($recordingPath);
        $fileMeta = $fs->getMetadata($recordingPath);

        try {
            $fileMime = $fs->getMimetype($recordingPath);
        } catch (\Exception $e) {
            $fileMime = 'application/octet-stream';
        }

        return $response->withFileDownload($fh, $filename, $fileMime)
            ->withHeader('Content-Length', $fileMeta['size'])
            ->withHeader('X-Accel-Buffering', 'no');
    }

    public function deleteAction(
        ServerRequest $request,
        Response $response,
        Filesystem $filesystem,
        $station_id,
        $id,
        $broadcast_id
    ): ResponseInterface {
        $station = $request->getStation();
        $broadcast = $this->getRecord($station, $broadcast_id);

        if (null === $broadcast) {
            return $response->withStatus(404)
                ->withJson(new Entity\Api\Error(404, __('Record not found!')));
        }

        $recordingPath = $broadcast->getRecordingPath();

        if (!empty($recordingPath)) {
            $fs = $filesystem->getForStation($station);
            $recordingPath = 'recordings://' . $recordingPath;

            $fs->delete($recordingPath);

            $broadcast->clearRecordingPath();
            $this->em->persist($broadcast);
            $this->em->flush();
        }

        return $response->withJson(new Entity\Api\Status);
    }

    protected function getRecord(Entity\Station $station, int $id): ?Entity\StationStreamerBroadcast
    {
        /** @var Entity\StationStreamerBroadcast|null $broadcast */
        $broadcast = $this->em->getRepository(Entity\StationStreamerBroadcast::class)->findOneBy([
            'id' => $id,
            'station' => $station,
        ]);
        return $broadcast;
    }

    protected function getStreamer(Entity\Station $station, int $id): ?Entity\StationStreamer
    {
        /** @var Entity\StationStreamer|null $streamer */
        $streamer = $this->em->getRepository(Entity\StationStreamer::class)->findOneBy([
            'id' => $id,
            'station' => $station,
        ]);
        return $streamer;
    }
}