<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace GraphJS\Controllers;

 use Psr\Http\Message\ResponseInterface;
 use Psr\Http\Message\ServerRequestInterface;

use Pho\Kernel\Kernel;
use PhoNetworksAutogenerated\User;
use PhoNetworksAutogenerated\Page;
use PhoNetworksAutogenerated\Blog;
use PhoNetworksAutogenerated\PrivateContent;
use PhoNetworksAutogenerated\UserOut\Star;
use PhoNetworksAutogenerated\UserOut\Comment;
use Pho\Lib\Graph\ID;

/**
 * Takes care of Content
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class ContentController extends AbstractController
{
    /**
     * Star 
     * 
     * [url]
     * 
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     
     * @param Kernel   $this->kernel
     * @param string   $id
     * 
     * @return void
     */
    public function star(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
                        'url' => 'required_without:id|url',
                        'id' => 'required_without:url',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Url or ID required.");
            
        }
        $i = $this->kernel->gs()->node($id);  
        //$page = $this->_fromUrlToNode($this->kernel, $data["url"]);
        if(isset($data["url"])&&!empty($data["url"]))  {
                    $page = $this->_fromUrlToNode($data["url"]);
                }
                else {
                    error_log("in id");
                    $page = $this->kernel->gs()->node($data["id"]);
                    if(!$page instanceof Page && !$page instanceof Blog) {
                        return $this->fail($response, "Can only star a Blog or Web Page.");
                    }
                }
        $i->star($page);    
        return $this->succeed(
            $response, [
            "count" => count($page->getStarrers())
            ]
        );
    }
 
    protected function _fromUrlToNode(string $url) 
    {
        $get_title = function(string $url): string 
        { // via https://stackoverflow.com/questions/4348912/get-title-of-website-via-link
            if(!function_exists("curl_init")) {
                $str = file_get_contents($url);
            }
            else {
                $ch =  curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                $str = curl_exec($ch);
            }
            if(strlen($str)>0){
              $str = trim(preg_replace('/\s+/', ' ', $str)); // supports line breaks inside <title>
              preg_match("/\<title\>(.*?)\<\/title\>/i",$str,$title); // ignore case
              return html_entity_decode($title[1]);
            }
            return "-";
        };
        $res = $this->kernel->index()->query("MATCH (n:page {Url: {url}}) RETURN n", ["url"=>$url]);
        if(count($res->results())==0) {
            return $this->kernel->founder()->post($url, $get_title($url));
        }
        return $this->kernel->gs()->node($res->results()[0]["n.udid"]);
    }
 
    public function isStarred(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'url' => 'required_without:id|url',
            'id' => 'required_without:url',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Url or ID required.");
            
        }
        if(isset($data["url"])&&!empty($data["url"]))  {
                        $page = $this->_fromUrlToNode($data["url"]);
                    }
                    else {
                        error_log("in id");
                        $page = $this->kernel->gs()->node($data["id"]);
                        if(!$page instanceof Page && !$page instanceof Blog) {
                            return $this->fail($response, "Can only star a Blog or Web Page.");
                        }
                    }
          $starrers = $page->getStarrers();
          $me = $this->dependOnSession($request, $response);
          return $this->succeed(
              $response, [
              "count"=>count($starrers), 
              "starred"=>is_null($me) ? false : $page->hasStarrer(ID::fromString($me))]
          );
    }

 
    public function editComment(ServerRequestInterface $request, ResponseInterface $response) 
    {
     if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
        return $this->failSession($response);
        }
     $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
            'content' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Comment ID and Content are required.");
            
        }
        $i = $this->kernel->gs()->node($id);
        $entity = $this->kernel->gs()->entity($data["id"]);
        if(!$entity instanceof Comment) {
            return $this->fail($response, "Given ID is not a Comment.");
            
        }
        try {
        $i->edit($entity)->setContent($data["content"]);
        }
     catch(\Exception $e) {
        return $this->fail($response, $e->getMessage());
            
     }
     return $this->succeed($response);
    }

    public function addComment(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'url' => 'required_without:id|url',
            'id' => 'required_without:url',
            'content' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "(Url or id) and content fields are required.");
            
        }
        $i = $this->kernel->gs()->node($id); 
        if(isset($data["url"])&&!empty($data["url"]))  {
            $page = $this->_fromUrlToNode($data["url"]);
            
        }
        else {
            $page = $this->kernel->gs()->node($data["id"]);
            if(!$page instanceof Page && !$page instanceof Blog) {
                return $this->fail($response, "Can only comment on Blog or Web Page.");
            }
        }
        $comment = $i->comment(
            $page, 
            $data["content"], 
            (
                $id != $this->kernel->founder()->id()->toString()  // it's not the founder
                && 
                $this->kernel->graph()->getCommentsModerated() === true // it's not moderated
            )
        );
        return $this->succeed($response, ["comment_id"=>$comment->id()->toString()]);
    }

    public function getComments(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'url' => 'required_without:id|url',
            'id' => 'required_without:url',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Url or ID field is required.");
            
        }
        if(isset($data["url"])&&!empty($data["url"])) {
            $page = $this->_fromUrlToNode($data["url"]);
        }
        else {
            $page = $this->kernel->gs()->node($data["id"]);
            if(!$page instanceof Page && !$page instanceof Blog) {
                return $this->fail($response, "Can only comment on Blog or Web Page.");
            }
        }
         $comments = array_map(
                function ($val) { 
                    $ret = [];
                    $attributes = $val->attributes()->toArray();
                    foreach($attributes as $k=>$v) {
                        $ret[\lcfirst($k)] = $v;
                    }
                    $ret['author'] = (string) $val->tail()->id();
                    
                    return [$val->id()->toString() => $ret];
                }, 
                $this->kernel->graph()->getCommentsModerated() === true ? 
                    array_filter($page->getComments(), function(Comment $comm) {
                        return (true !== $comm->getPending());
                    })
                    : $page->getComments()
         );
         return $this->succeed(
             $response, [
                "comments"=>$comments
             ]
         );
    }

    public function removeComment(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'comment_id' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Comment_id field is required.");
            
        }
        $i = $this->kernel->gs()->node($id);  
        if(!$i->hasComment(ID::fromString($data["comment_id"]))) {
            return $this->fail($response, "Comment_id does not belong to you.");
            
        }
        $comment = $this->kernel->gs()->edge($data["comment_id"]);
        $comment->destroy();
        return $this->succeed($response);
    }
 
    public function unstar(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'url' => 'required_without:id|url',
            'id' => 'required_without:url',
        ]);

        if($validation->fails()) {
            return $this->fail($response, "Url or ID required.");
            
        }
        $i = $this->kernel->gs()->node($id);  
        if(isset($data["url"])&&!empty($data["url"]))  {
            $page = $this->_fromUrlToNode($data["url"]);
        }
        else {
        error_log("in id");
            $page = $this->kernel->gs()->node($data["id"]);
            if(!$page instanceof Page && !$page instanceof Blog) {
                return $this->fail($response, "Can only star a Blog or Web Page.");
            }
        }
        $stars = iterator_to_array($i->edges()->between($page->id(), Star::class));
        error_log("Total star count: ".count($stars));
        foreach($stars as $star) {
            error_log("Star ID: ".$star->id()->toString());
            $star->destroy();
        }
        return $this->succeed($response);
    }
 
    /**
     * Fetch starred content
     *
     * @param ServerRequestInterface  $request
     * @param ResponseInterface $response
     
     * @param Kernel   $this->kernel
     * 
     * @return void
     */
    public function getStarredContent(ServerRequestInterface $request, ResponseInterface $response)
    {
        $res = $this->kernel->index()->query("MATCH ()-[e:star]->(n:page) WITH n.Url AS content, n.Title AS the_title, count(e) AS star_count RETURN the_title, content, star_count ORDER BY star_count");
        $array = $res->results();
        $ret = [];
        foreach($array as $a) {
            //$ret[$a->value("content")] = $a->value("star_count");
            $ret[$a["content"]] = [
                "title" => $a["the_title"], 
                "star_count" => $a["star_count"]
            ];
        }
        if(count($array)==0) {
            return $this->fail($response, "No content starred yet");
        }
        return $this->succeed($response, ["pages"=>$ret]);
    }

    public function getMyStarredContent(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $res = $this->kernel->index()->query(
            "MATCH (:user {udid: {me}})-[e:star]->(n:page) WITH n.Url AS content, n.Title AS the_title, count(e) AS star_count RETURN the_title, content, star_count ORDER BY star_count", 
            array("me"=>$id)
        );
        $array = $res->results();
        $ret = [];
        foreach($array as $a) {
            $ret[$a["content"]] = [
                "title" => $a["the_title"], 
                "star_count" => $a["star_count"]
            ];
        }
        if(count($array)==0) {
            return $this->fail($response, "No content starred yet");
        }
        return $this->succeed($response, ["pages"=>$ret]);
    }

    public function addPrivateContent(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'data' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "Data field is required.");
            
        }
        $i = $this->kernel->gs()->node($id);  
        try {
            $private_content = $i->postPrivateContent($data["data"]);
            //$private_content = $i->post("http://private/?".bin2hex(random_bytes(16)), $data["data"]);
            return $this->succeed($response, ["id"=>(string) $private_content->id()]);
        }
        catch (\Exception $e) {
            return $this->fail($response, "Unknown error creating private content. Try again later.");
        }
    }

    public function editPrivateContent(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
            'data' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "ID and Data fields are required.");
            
        }
        $i = $this->kernel->gs()->node($id); 
        try {
            $private_content = $this->kernel->gs()->node($data["id"]);
            if($private_content instanceof Page)
                $i->edit($private_content)->setTitle($data["data"]);
            else // instanceof PrivateContent
                $i->edit($private_content)->setContent($data["data"]);
            return $this->succeed($response);
        } 
        catch (\Exception $e) {
            return $this->fail($response, "Invalid ID");
        }
    }

    public function getPrivateContent(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "ID is required.");
        }
        try {
            $private_content = $this->kernel->gs()->node($data["id"]);
            $contents = "";
            if($private_content instanceof Page) {
                $contents =    $private_content->getTitle();
            }
            elseif($private_content instanceof PrivateContent) {
                $contents = $private_content->getContent();
            }
            else {
                return $this->fail($response, "Invalid ID");
            }
            return $this->succeed($response, ["contents"=>$contents]);
        }
        catch (\Exception $e) {
            return $this->fail($response, "Invalid ID");
        }
    }

    public function listPrivateContents(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $all = $this->kernel->graph()->members();
        $private_pages = array_filter($all, function($val) {
            return ($val instanceof PrivateContent);
        });
        $contents = [];
        foreach($private_pages as $content) {
            $contents[$content->id()->toString()] = substr($content->getContent(), 0, 35);
        }
        return $this->succeed($response, ["contents"=>$contents]);
    }

    public function deletePrivateContent(ServerRequestInterface $request, ResponseInterface $response)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->failSession($response);
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
        ]);
        if($validation->fails()) {
            return $this->fail($response, "ID is required.");
            
        }
        try {
            $i = $this->kernel->gs()->node($id);
            $private_content = $this->kernel->gs()->node($data["id"]);
            if(!$private_content instanceof Page && !$private_content instanceof PrivateContent) {
                return $this->fail($response, "Invalid ID");
            }
            // check author
            if(
                !$i->id()->equals($this->kernel->founder()->id()) 
                &&
                !$private_content->edges()->in()->current()->tail()->node()->id()->equals($i->id())
            ) {
                return $this->fail($response, "No privileges to delete this content");
            }
            $private_content->destroy();
            return $this->succeed($response);
        }
        catch (\Exception $e) {
            return $this->fail($response, "Invalid ID");
        }
    }

    public function addRating()
    {

    }

    public function delRating()
    {
        
    }

}
