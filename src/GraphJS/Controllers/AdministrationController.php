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

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use Valitron\Validator;
use PhoNetworksAutogenerated\User;
use PhoNetworksAutogenerated\UserOut\Star;
use PhoNetworksAutogenerated\UserOut\Comment;
use Pho\Lib\Graph\ID;


/**
 * Administrative calls go here.
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class AdministrationController extends AbstractController
{

    protected function requireAdministrativeRights(Request $request, Response $response, Session $session, Kernel $kernel): bool
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return false;
        }
        if($id!=$kernel->founder()->id()->toString()) {
            $this->fail($response, "You need administrative privileges to run this call.");
            return false;
        }
        return true;
    }


    public function fetchAllPendingComments(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights())
            return;
        $is_moderated = ($kernel->graph()->getCommentsModerated() === true);
        if(!$is_moderated)
            return $this->fail($response);
        $pending_comments = [];
        // index'ten cekecegiz
        $res = $kernel->index()->client()->run("MATCH ()-[e:comment {Pending: true}]-(n:page) RETURN n.udid AS page_id, e.udid AS comment_id, n.Url AS page_url, n.Title AS page_title, e.Content AS comment");
        $array = $res->records();
        $ret = [];
        foreach($array as $a) {
            $ret[$a->value("comment_id")] = [
                "page_id" => $a->value("page_id"), 
                "page_url" => $a->value("page_url"),
                "page_title" => $a->value("page_title"),
                "comment" => $a->value("comment"),
            ];
        }
        $this->succeed($response, ["pending_comments"=>$ret]);
    }

    /**
     * @todo Check for admin capabilities
     */
    public function approvePendingComment(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights())
            return;
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['comment_id']);
        if(!$v->validate()) {
            $this->fail($response, "comment_id required");
            return;
        }
        try {
            $comment = $kernel->gs()->edge($data["comment_id"]);
        }
        catch(\Exception $e) {
            $this->fail($response, "Invalid Comment ID.");
            return;
        }
        if(!$comment instanceof Comment)
            return $this->fail($response, "Invalid Comment.");
        $comment->setPending(false);
        $this->succeed($response);
    }

    public function setCommentModeration(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights())
            return;
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['moderated']);
        $v->rule('boolean', ['moderated']);
        if(!$v->validate()) {
            return $this->fail($response, "A boolean 'moderated' field is required");
        }
        $is_moderated = (bool) $data["moderated"];
        $kernel->graph()->setCommentsModerated($is_moderated);
        $kernel->graph()->persist();
        $this->succeed($response);
    }

}