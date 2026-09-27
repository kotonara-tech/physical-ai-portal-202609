<?php

declare(strict_types=1);

namespace Drupal\soarm_vote\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\soarm_vote\VoteManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves the JSON vote API at /api/soarm/vote/{node}.
 */
final class VoteApiController implements ContainerInjectionInterface {

  public function __construct(
    private readonly VoteManagerInterface $voteManager,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soarm_vote.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * Returns vote counts and the current user's votes for a node.
   */
  public function counts(NodeInterface $node): JsonResponse {
    return $this->stateResponse($node, 200);
  }

  /**
   * Casts (POST) or withdraws (DELETE) a vote from the request body.
   */
  public function cast(NodeInterface $node, Request $request): JsonResponse {
    $type = $this->extractType($request);
    if ($type === NULL) {
      return $this->errorResponse('Request body must be a JSON object with a "type" property set to one of: ' . implode(', ', VoteManagerInterface::TYPES) . '.');
    }

    if ($request->getMethod() === 'DELETE') {
      $this->voteManager->withdraw($node, $this->currentUser, $type);
      return $this->stateResponse($node, 200);
    }

    $added = $this->voteManager->cast($node, $this->currentUser, $type);
    return $this->stateResponse($node, $added ? 201 : 200);
  }

  /**
   * Extracts and validates the vote "type" from a JSON request body.
   */
  private function extractType(Request $request): ?string {
    $content = $request->getContent();
    if (!is_string($content) || $content === '') {
      return NULL;
    }

    try {
      $data = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return NULL;
    }

    if (!is_array($data) || !isset($data['type']) || !is_string($data['type'])) {
      return NULL;
    }

    if (!in_array($data['type'], VoteManagerInterface::TYPES, TRUE)) {
      return NULL;
    }

    return $data['type'];
  }

  /**
   * Builds the standard {counts, my_votes} JSON response.
   */
  private function stateResponse(NodeInterface $node, int $status): JsonResponse {
    return $this->jsonResponse([
      'counts' => $this->voteManager->counts($node),
      'my_votes' => $this->voteManager->typesCastBy($node, $this->currentUser),
    ], $status);
  }

  /**
   * Builds a 400 error response.
   */
  private function errorResponse(string $message): JsonResponse {
    return $this->jsonResponse(['error' => $message], 400);
  }

  /**
   * Builds a never-cached JSON response.
   */
  private function jsonResponse(array $data, int $status): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'no-cache, private');

    return $response;
  }

}
