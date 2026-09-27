<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_vote\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the JSON vote API at /api/soarm/vote/{node} and the vote display.
 */
#[Group('soarm_vote')]
final class VoteApiTest extends KernelTestBase {

  use UserCreationTrait;

  private const ZERO = ['useful' => 0, 'improvement' => 0, 'replication' => 0];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'soarm_vote'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('soarm_vote');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'filter', 'node']);
    NodeType::create(['type' => 'robot_knowledge', 'name' => 'Robot knowledge'])->save();
    // The first created user is uid 1 (bypasses access); burn it.
    $this->createUser();
  }

  /**
   * Creates a robot knowledge post.
   */
  private function createPost(bool $published = TRUE): NodeInterface {
    $node = Node::create(['type' => 'robot_knowledge', 'title' => 'post', 'status' => (int) $published]);
    $node->save();
    return $node;
  }

  /**
   * Sends a request through the HTTP kernel as the current user.
   */
  private function call(string $method, NodeInterface $node, ?string $body = NULL): Response {
    $request = Request::create('/api/soarm/vote/' . $node->id(), $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    return $this->container->get('http_kernel')->handle($request);
  }

  /**
   * Decodes a JSON response body.
   */
  private function json(Response $response): array {
    return json_decode((string) $response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Tests that anyone who can see the post can read its vote counts.
   */
  public function testAnyoneWhoCanSeeThePostCanReadCounts(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $response = $this->call('GET', $this->createPost());

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['counts' => self::ZERO, 'my_votes' => []], $this->json($response));
  }

  /**
   * Tests that reading vote counts of an unpublished post is forbidden.
   */
  public function testCountsOfAnUnpublishedPostAreForbidden(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $this->assertSame(403, $this->call('GET', $this->createPost(FALSE))->getStatusCode());
  }

  /**
   * Tests that a POST request casts a vote and returns the new counts.
   */
  public function testPostCastsVote(): void {
    $this->setCurrentUser($this->createUser(['access content', 'cast soarm votes']));
    $node = $this->createPost();

    $response = $this->call('POST', $node, '{"type":"useful"}');

    $this->assertSame(201, $response->getStatusCode());
    $this->assertSame(['counts' => ['useful' => 1] + self::ZERO, 'my_votes' => ['useful']], $this->json($response));
  }

  /**
   * Tests that repeating the same vote is OK but does not create a duplicate.
   */
  public function testRepeatedVoteIsOkButNotCreated(): void {
    $this->setCurrentUser($this->createUser(['access content', 'cast soarm votes']));
    $node = $this->createPost();
    $this->call('POST', $node, '{"type":"useful"}');

    $response = $this->call('POST', $node, '{"type":"useful"}');

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(1, $this->json($response)['counts']['useful']);
  }

  /**
   * Tests that a DELETE request withdraws a vote and returns the new counts.
   */
  public function testDeleteWithdrawsVote(): void {
    $this->setCurrentUser($this->createUser(['access content', 'cast soarm votes']));
    $node = $this->createPost();
    $this->call('POST', $node, '{"type":"replication"}');

    $response = $this->call('DELETE', $node, '{"type":"replication"}');

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['counts' => self::ZERO, 'my_votes' => []], $this->json($response));
  }

  /**
   * Tests that unknown vote types and malformed bodies are bad requests.
   */
  public function testUnknownTypeAndMalformedBodiesAreBadRequests(): void {
    $this->setCurrentUser($this->createUser(['access content', 'cast soarm votes']));
    $node = $this->createPost();

    foreach (['{"type":"spam"}', '{"type":["useful"]}', '{}', 'not json', NULL] as $body) {
      $this->assertSame(400, $this->call('POST', $node, $body)->getStatusCode(), var_export($body, TRUE));
    }
  }

  /**
   * Tests that voting needs the "cast soarm votes" permission.
   */
  public function testVotingNeedsThePermission(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $this->assertSame(403, $this->call('POST', $this->createPost(), '{"type":"useful"}')->getStatusCode());
  }

  /**
   * Tests that anonymous users cannot vote even with the permission granted.
   */
  public function testAnonymousCannotVoteEvenWithThePermission(): void {
    Role::create(['id' => RoleInterface::ANONYMOUS_ID, 'label' => 'Anonymous'])
      ->grantPermission('access content')
      ->grantPermission('cast soarm votes')
      ->save();
    $this->setCurrentUser(new AnonymousUserSession());

    $this->assertSame(403, $this->call('POST', $this->createPost(), '{"type":"useful"}')->getStatusCode());
  }

  /**
   * Tests that vote count responses are never cached across users.
   */
  public function testResponsesAreNeverCachedAcrossUsers(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $response = $this->call('GET', $this->createPost());

    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
  }

  /**
   * Tests that the detail page shows vote counts and refreshes after a vote.
   */
  public function testDetailPageShowsCountsAndRefreshesAfterVote(): void {
    $voter = $this->createUser(['access content', 'cast soarm votes']);
    $this->setCurrentUser($voter);
    $node = $this->createPost();

    $this->assertSame(['useful' => '0', 'improvement' => '0', 'replication' => '0'], $this->renderedCounts($node));

    $this->container->get('soarm_vote.manager')->cast($node, $voter, 'improvement');

    $this->assertSame(['useful' => '0', 'improvement' => '1', 'replication' => '0'], $this->renderedCounts($node));
  }

  /**
   * Renders the post (through the render cache) and extracts shown counts.
   *
   * @return string[]
   *   Count text keyed by vote type.
   */
  private function renderedCounts(NodeInterface $node): array {
    $build = $this->container->get('entity_type.manager')->getViewBuilder('node')->view($node, 'full');
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    $document = new \DOMDocument();
    @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $xpath = new \DOMXPath($document);
    $counts = [];
    foreach ($xpath->query('//*[@data-soarm-vote]') as $element) {
      $count = $xpath->query('.//*[@data-soarm-vote-count]', $element)->item(0);
      $counts[$element->getAttribute('data-soarm-vote')] = $count ? trim($count->textContent) : NULL;
    }
    return $counts;
  }

}
