<?php

    class socialxeController extends socialxe {

        /**
         * @brief 초기화
         **/
        function init() {
        }

        // 자동 로그인 키 세팅
        function procSocialxeSetAutoLoginKey(){
            $auto_login_key = Context::get('auto_login_key');
            $widget_skin = Context::get('skin'); // 위젯의 스킨명

            // 세팅
            $this->communicator->setAutoLoginKey($auto_login_key);

            // 입력창 컴파일
            $output = $this->_compileInput();
            $this->add('skin', $widget_skin);
            $this->add('output', $output);
        }

        // 대표 계정 설정
        function procSocialxeChangeMaster(){
            $widget_skin = Context::get('skin'); // 위젯의 스킨명
            $provider = Context::get('provider'); // 서비스

            $this->providerManager->setMasterProvider($provider);
            $this->communicator->sendSession();

            // 입력창 컴파일
            $output = $this->_compileInput();
            $this->add('skin', $widget_skin);
            $this->add('output', $output);
        }

        // 댓글 달기
        function procSocialxeInsertComment(){
            $oCommentController = &getController('comment');

            // 로그인 상태인지 확인
            if (count($this->providerManager->getLoggedProviderList()) == 0){
                return $this->stop('msg_not_logged');
            }

            $args->document_srl = Context::get('document_srl');

            // 해당 문서의 댓글이 닫혀있는지 확인
            $oDocumentModel = &getModel('document');
            $oDocument = $oDocumentModel->getDocument($args->document_srl);
            if (!$oDocument->allowComment()) return new Object(-1, 'msg_invalid_request');

            // 데이터를 준비
            $args->parent_srl = Context::get('comment_srl');
            $args->content = Context::get('content');
            $args->nick_name = $this->providerManager->getMasterProviderNickName();
            $args->content_link = Context::get('content_link');
            $args->content_title = Context::get('content_title');

            // 댓글의 moduel_srl
            $oModuleModel = &getModel('module');
            $module_info = $oModuleModel->getModuleInfoByDocumentSrl($args->document_srl);
            $args->module_srl = $module_info->module_srl;

            // 댓글 삽입

            // XE가 대표 계정이면 XE 회원 정보를 이용하여 댓글을 등록
            if ($this->providerManager->getMasterProvider() == 'xe'){
                $manual_inserted = false;
                // 부계정이 없으면 알림 설정
                if (!$this->providerManager->getSlaveProvider())
                    $args->notify_message = "Y";
            }else{
                $manual_inserted = true;
            }

            $result = $oCommentController->insertComment($args, $manual_inserted);
            if (!$result->toBool()) return $result;

            // 삽입된 댓글의 번호
            $comment_srl = $result->get('comment_srl');

            // 소셜 서비스로 댓글 전송
            $output = $this->communicator->sendComment($args);
            if (!$output->toBool()){
                $oCommentController->deleteComment($comment_srl);
                return $output;
            }
            $msg = $output->get('msg');

            // 추가 정보 준비
            $args->comment_srl = $comment_srl;

            // 대표 계정이 XE면 부계정의 정보를 넣는다.
            if ($this->providerManager->getMasterProvider() == 'xe'){
                $args->provider = $this->providerManager->getSlaveProvider();
                $args->id = $this->providerManager->getSlaveProviderId();
                $args->comment_id = $output->get('comment_id');
                $args->social_nick_name = $this->providerManager->getSlaveProviderNickName();
            }

            // 대표 계정이 XE가 아니면 대표 계정의 정보를 넣는다.
            else{
                $args->provider = $this->providerManager->getMasterProvider();
                $args->id = $this->providerManager->getMasterProviderId();
                $args->profile_image = $this->providerManager->getMasterProviderProfileImage();
                $args->comment_id = $output->get('comment_id');
                $args->social_nick_name = $this->providerManager->getMasterProviderNickName();
            }

            // 추가 정보 삽입
            $output = executeQuery('socialxe.insertSocialxe', $args);
            if (!$output->toBool()){
                $oCommentController->deleteComment($comment_srl);
                return $output;
            }

            // 위젯에서 화면 갱신에 사용할 정보 세팅
            $this->add('skin', Context::get('skin'));
            $this->add('document_srl', Context::get('document_srl'));
            $this->add('comment_srl', Context::get('comment_srl'));
            $this->add('list_count', Context::get('list_count'));
            $this->add('content_link', Context::get('content_link'));
            $this->add('msg', $msg);
        }

        // 댓글 삭제
        function procSocialxeDeleteComment(){
            $comment_srl = Context::get('comment_srl');
            if (!$comment_srl) return $this->stop('msg_invalid_request');

            // 우선 SocialCommentItem을 만든다.
            // DB에서 읽어오게 되지만, 어차피 권한 체크하려면 읽어야 한다.
            $oComment = new socialCommentItem($comment_srl);

            // comment 모듈의 controller 객체 생성
            $oCommentController = &getController('comment');

            $output = $oCommentController->deleteComment($comment_srl, $oComment->isGranted());
            if(!$output->toBool()) return $output;

            // 위젯에서 화면 갱신에 사용할 정보 세팅
            $this->add('skin', Context::get('skin'));
            $this->add('document_srl', Context::get('document_srl'));
            $this->add('comment_srl', Context::get('comment_srl'));
            $this->add('list_count', Context::get('list_count'));
            $this->add('content_link', Context::get('content_link'));

            $this->setMessage('success_deleted');
        }

        // 입력창 컴파일
        function procCompileInput(){
            $this->add('output', $this->_compileInput());
        }

        function _compileInput(){
            $skin = Context::get('skin');

            // socialxe_comment 위젯을 구한다.
            $oWidgetController = &getController('widget');
            $widget = $oWidgetController->getWidgetObject('socialxe_comment');
            if (!$widget)   return;

            $output = $widget->_compileInput($skin, urlencode($this->session->getSession('callback_query')));
            $this->session->clearSession('callback_query');

            return $output;
        }

        // 목록 컴파일
        function procCompileList(){
            $this->add('output', $this->_compileList());
        }

        function _compileList(){
            $skin = Context::get('skin');
            $document_srl = Context::get('document_srl');
            $last_comment_srl = Context::get('last_comment_srl');
            $list_count = Context::get('list_count');
            $content_link = Context::get('content_link');

            // socialxe_comment 위젯을 구한다.
            $oWidgetController = &getController('widget');
            $widget = $oWidgetController->getWidgetObject('socialxe_comment');
            if (!$widget)   return;

            return $output = $widget->_compileCommentList($skin, $document_srl, $content_link, $last_comment_srl, $list_count);
        }

        // 대댓글 컴파일
        function procCompileSubList(){
            $skin = Context::get('skin');
            $document_srl = Context::get('document_srl');
            $comment_srl = Context::get('comment_srl');
            $content_link = Context::get('content_link');
            $page = Context::get('page');

            // socialxe_comment 위젯을 구한다.
            $oWidgetController = &getController('widget');
            $widget = $oWidgetController->getWidgetObject('socialxe_comment');
            if (!$widget)   return;

            $output = $widget->_compileSubCommentList($skin, $document_srl, $comment_srl, $content_link, $page);

            $this->add('output', $output->get('output'));
            $this->add('comment_srl', $comment_srl);
            $this->add('total', $output->get('total'));
        }

        // 댓글 삭제 트리거
        function triggerDeleteComment(&$comment){
            if (!$comment->comment_srl) return new Object();

            $args->comment_srl = $comment->comment_srl;
            $output = executeQuery('socialxe.deleteSocialxe', $args);
            return $output;
        }
    }
?>
