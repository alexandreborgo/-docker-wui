<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Container;
use AppBundle\Entity\DCFile;
use AppBundle\Entity\Image;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;

class DefaultController extends Controller
{
    /**
     * @Route("/see/{what}", name="see")
     */
    public function seeAction(Request $request, $what)
    {
        $response = null;

        switch($what) {
            case 'containers':

                $repository = $this->getDoctrine()->getRepository(Container::class);

                // only current user container
                $containers = $repository->findBy(['owner' => $this->getUser()->getId()]);

                $response = $this->render('AppBundle:Default:see.html.twig', array('containers' => $containers));
                break;

            case 'images':

                $repository = $this->getDoctrine()->getRepository(Image::class);

                // all images
                $images = $repository->findAll();

                $response = $this->render('AppBundle:Default:see.html.twig', array('images' => $images));
                break;

            case 'files':

                $repository = $this->getDoctrine()->getRepository('AppBundle:DCFile');

                // all files
                $file = $repository->findAll();

                $contents = array();

                $finder = new Finder();
                foreach ($file as $fl) {
                    $finder->files()->in($fl->getPath());

                    foreach ($finder as $f)
                        $contents[$fl->getName()] = $f->getContents();
                }

                $response = $this->render('AppBundle:Default:see.html.twig', array('files' => $file, 'contents' => $contents));
                break;

            default:
                throw $this->createNotFoundException('Error 404');
                break;
        }

        return $response;
    }

    /**
     * @Route("/add/{what}/{how}", name="add")
     */
    public function addAction(Request $request, $what, $how)
    {
        if($what == 'image') {
            if($how == 'pull') {

                // comment can be empty
                if(!empty($request->request->get('repository')) && !empty($request->request->get('tag'))) {

                    // valid char
                    $pattern = '/[\'\/~`\!@#\$%\^&\*\(\)_\+=\{\}\[\]\|;:"\<\>,\?\\\]/';

                    if (preg_match($pattern, $request->request->get('repository')) OR
                        preg_match($pattern, $request->request->get('tag')))
                    {
                        return $this->render('AppBundle:Default:add.html.twig',
                            array('image'=> 'pull',
                                'message_error' => 'The image\'s repository or tag contains special characters which isn\'t allowed.'
                            ));
                    }

                    $repository = $this->getDoctrine()->getRepository(Image::class);
                    $image = $repository->findOneBy(
                        array(  'repository' => $request->request->get('repository'),
                                'tag' => $request->request->get('tag')
                    ));

                    // this image is already present
                    if($image) {
                        return $this->render('AppBundle:Default:add.html.twig',
                            array('image'=> 'pull',
                                'message_error' => 'The image ' . $request->request->get('repository') . ':' . $request->request->get('tag') . ' is already present.'
                            ));
                    }
                    else {
                        $new_image = new Image();
                        $new_image->setImageId("unknown"); // completed after the download
                        $new_image->setRepository($request->request->get('repository'));
                        $new_image->setTag($request->request->get('tag'));
                        $new_image->setComment($request->request->get('comment'));
                        $new_image->setIsfromdf(false);
                        $new_image->setPath("none");
                        $new_image->setStatut(1);
                        $new_image->setSize("unknown"); // completed after the download

                        $dm = $this->getDoctrine()->getManager();
                        $dm->persist($new_image);
                        $dm->flush();

                        // call download script, in parallel to not slow the page
                        shell_exec("nohup wget -q " . (isset($_SERVER["HTTPS"]) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . $this->generateUrl('script', array('action' => 'pull', 'image' => $new_image->getId())) . " > log 2>&1 &");
                    }

                    return $this->render('AppBundle:Default:add.html.twig',
                        array('image'=> 'pull',
                              'message_accept' => 'The image ' . $request->request->get('repository') . ':' . $request->request->get('tag') . ' will be downloaded soon.'
                        ));
                }

                // here we check if the form was posted or not
                // in order to display an error if it is
                if(!empty($request->request->get('repository')) or !empty($request->request->get('tag'))) {
                    return $this->render('AppBundle:Default:add.html.twig',
                        array('image' => 'pull',
                            'message_error' => 'The repository field and tag field can\'t be empty'
                        ));
                }

                return $this->render('AppBundle:Default:add.html.twig', array('image' => 'pull'));
            }
        }
        else if($what == 'container') {
            if($how == 'fromimage') {

                $dm = $this->getDoctrine()->getManager();
                $repository = $dm->getRepository(Image::class);
                $images = $repository->findAll();

                if(!empty($request->request->get('name')) and !empty($request->request->get('image')) and !empty($request->request->get('hostp')) and !empty($request->request->get('guestp'))) {

                    if(strpos($request->request->get('name'), " ") !== false) {
                        return $this->render('AppBundle:Default:add.html.twig', array(
                            'container' => 'fromimage',
                            'message_error' => 'The name of the container can\'t contain space.',
                            'images' => $images
                        ));
                    }

                    $new_container = new Container();
                    $new_container->setContainerId("unknown");
                    $new_container->setName($request->request->get('name'));
                    $new_container->setComment($request->request->get('comment'));
                    $new_container->setHostP($request->request->get('hostp'));
                    $new_container->setGuestP($request->request->get('guestp'));
                    $new_container->setIsrunning(true);
                    $new_container->setOwner($this->getUser()->getId());

                    $image = $repository->findOneById($request->request->get('image'));

                    if(!$image) {
                        return $this->render('AppBundle:Default:add.html.twig', array(
                            'container' => 'fromimage',
                            'message_error' => 'Unknown image.',
                            'images' => $images
                        ));
                    }

                    $new_container->setImage($image);

                    $result = shell_exec("docker run -dit --name " . $new_container->getName() . " -p " . $new_container->getHostP() . ":" . $new_container->getGuestP() . " " . $image->getRepository(). ":" . $image->getTag() . " 2>&1");

                    if(strpos($result, "Error") !== false) {

                        if(strpos($result, "The container name") !== false) {

                            return $this->render('AppBundle:Default:add.html.twig', array(
                                'container' => 'fromimage',
                                'message_error' => 'Erreur : name of the container must be unique.',
                                'images' => $images
                            ));
                        }
                        else if(strpos($result, "port is already allocated") !== false) {

                            shell_exec("docker rm " . $request->request->get('name'));

                            return $this->render('AppBundle:Default:add.html.twig', array(
                                'container' => 'fromimage',
                                'message_error' => "Erreur : port " . $new_container->getHostP() . " is already binded to an other container.",
                                'images' => $images
                            ));

                        }

                        return $this->render('AppBundle:Default:add.html.twig', array(
                            'container' => 'fromimage',
                            'message_error' => 'Error : ' . $result,
                            'images' => $images
                        ));
                    }
                    else {
                        $id = shell_exec("docker ps -a --filter \"name=" . $new_container->getName() . "\" --format \"{{ .ID }}\"");
                        $new_container->setContainerId($id);
                        $new_container->setStatut(0);
                        $dm->persist($new_container);
                        $dm->flush();
                    }

                    return $this->render('AppBundle:Default:add.html.twig', array(
                        'container' => 'true',
                        'message_accept' => 'The container has been created.',
                        'images' => $images));
                }

                if(!empty($request->request->get('name')) or !empty($request->request->get('image'))) {
                    return $this->render('AppBundle:Default:add.html.twig',
                        array('container' => 'fromimage',
                            'message_error' => 'The name field, image field and ports field can\'t be empty',
                            'images' => $images
                        ));
                }

                return $this->render('AppBundle:Default:add.html.twig', array('container' => 'fromimage', 'images' => $images));
            }
        }
        else if($what == 'file') {

            $file = $request->files->get('file');

            if(!empty($request->request->get('name')) && $request->request->get('type') != '0' && $file != null) {

                $new_file = new DCFile();
                $new_file->setName($request->request->get('name'));
                $new_file->setType($request->request->get('type'));
                $new_file->setComment("");

                if ($request->request->get('type') == 1) {
                    $file->move(
                        $this->getParameter('dockerfile_dir') . "/" . $request->request->get('name'),
                        "dockerfile"
                    );

                    $new_file->setPath($this->getParameter('dockerfile_dir') . "/" . $request->request->get('name'));
                }
                else {
                    $file->move(
                        $this->getParameter('dockercompose_dir') . "/" . $request->request->get('name'),
                        "docker-compose.yml"
                    );

                    $new_file->setPath($this->getParameter('dockercompose_dir') . "/" . $request->request->get('name'));
                }

                $dm = $this->getDoctrine()->getManager();
                $dm->persist($new_file);
                $dm->flush();


                return $this->render('AppBundle:Default:add.html.twig',
                    array('file'=> 'download',
                        'message_accept' => 'The file ' . $request->request->get('name') . ' has been uploaded.'
                    ));
            }

            if(!empty($request->request->get('name')) or !empty($request->request->get('file')) or $request->request->get('type') == '0') {
                return $this->render('AppBundle:Default:add.html.twig',
                    array('file' => 'download',
                        'message_error' => 'The name field and file input can\'t be empty, the type must be set to dockerfile or dockercompose.yml'
                    ));
            }

            return $this->render('AppBundle:Default:add.html.twig', array('file' => 'true'));
        }
    }

    /**
     * @Route("/edit/{object}/{id}/{action}", name="edit")
     */
    public function editAction(Request $request, $id, $object, $action)
    {
        if($object == 'image') {
            $dm = $this->getDoctrine()->getManager();
            $image = $dm->getRepository(Image::class)->find($id);

            // the image exist
            if ($image) {
                if($action == 1) {
                    // the image was downloaded so we active it
                    $image->setStatut(0);
                    $dm->flush();
                }
                else if($action == 2) {
                    // edit the comment on the image
                    // no check on the comment (empty or not)
                    // we may want to delete the comment
                    $image->setComment($request->request->get('comment'));
                    $dm->flush();

                    return $this->render('AppBundle:Default:edit.html.twig', array('image' => $image, 'message_accept' => 'true'));
                }
            }
        }

        return $this->render('AppBundle:Default:edit.html.twig', array('message_error' => 'The image couldn\'t be edited.'));
    }

    /**
     * @Route("/delete/{object}/{id}", name="delete")
     */
    public function deleteAction(Request $request, $object, $id)
    {
        if($object == 'image') {
            $dm = $this->getDoctrine()->getManager();
            $image = $dm->getRepository(Image::class)->find($id);

            // if image does not exist return error
            if($image == null) {
                return $this->render('AppBundle:Default:delete.html.twig', array('image' => $image,
                    'message_error' => 'The image with id : ' . $id . ' doesn\'t exist.'));
            }

            // we try to destroy the image
            $result = shell_exec("docker rmi " . $image->getRepository() . ":" . $image->getTag() . " 2>&1");

            // fail return error or suceed delete into the bdd
            if(strpos($result, "conflict") !== false) {
                return $this->render('AppBundle:Default:delete.html.twig', array('image' => $image,
                    'message_error' => 'The image ' . $image->getRepository() . ':' . $image->getTag(). ' couldn\'t be removed because there\'s a container using it.'));
            }
            else {
                $dm->remove($image);
                $dm->flush();
                return $this->render('AppBundle:Default:delete.html.twig', array('image' => $image,
                    'message_accept' => 'The image has been deleted.'));
            }
        }
        else if($object == 'container') {
            $dm = $this->getDoctrine()->getManager();
            $container = $dm->getRepository(Container::class)->find($id);

            // if the container does not exist we return an error
            if($container == null) {
                return $this->render('AppBundle:Default:delete.html.twig', array('container' => $container,
                    'message_error' => 'The container with id : ' . $id . ' doesn\'t exist.'));
            }

            // can only fail if the container doesn't exist so it isn't a problem
            shell_exec("docker stop " . $container->getContainerId() . " 2>&1");
            shell_exec("docker rm " . $container->getContainerId() . " 2>&1");

            $dm->remove($container);
            $dm->flush();
            return $this->render('AppBundle:Default:delete.html.twig', array('container' => $container,
                'message_accept' => 'Container has been deleted.'));
        }
        else if($object == 'file') {
            $dm = $this->getDoctrine()->getManager();
            $file = $dm->getRepository(DCFile::class)->find($id);

            // the file doesn't exist
            if($file == null) {
                return $this->render('AppBundle:Default:delete.html.twig', array('file' => $file,
                    'message_error' => 'The file with id : ' . $id . ' doesn\'t exist.'));
            }

            $dm->remove($file);
            $dm->flush();

            return $this->render('AppBundle:Default:delete.html.twig', array('file' => $file, 'message_accept' => 'File has been deleted.'));
        }

        // if reached there's a problem in the url
        throw $this->createNotFoundException('Error 404');
    }

    /**
     * @Route("/script/{action}/{image}", name="script")
     */
    public function scriptAction(Request $request, $action, $image)
    {
        $dm = $this->getDoctrine()->getManager();

        if($action == 'pull') {

            $imageObject = $dm->getRepository(Image::class)->find($image);

            // the image doesn't exist
            if($imageObject == null) {
                // as this script is executed in parallel no need to return anything
                exit;
            }

            // pull the image
            $result = shell_exec("docker pull " . $imageObject->getRepository() . ":" . $imageObject->getTag() . " 2>&1");

            // image doesn't exist on docker hub
            if (strpos($result, "Error") !== false) {
                $imageObject->setStatut(-1);
                $imageObject->setComment("repository or tag not found");
                $dm->flush();
            }
            else {
                $idsize = shell_exec("docker images " . $imageObject->getRepository() . ":" . $imageObject->getTag() . " --format \"{{.ID}}:{{.Size}}\"");
                list($id, $size) = explode(":", $idsize);

                $imageObject->setImageId($id);
                $imageObject->setSize($size);
                $imageObject->setStatut(0);
                $dm->flush();
            }
        }
        else if($action == 'start') {
            $container = $dm->getRepository(Container::class)->find($image);

            // container doesn't exist
            if(!$container) {
                return $this->render('AppBundle:Default:delete.html.twig', array('container' => 'toto',
                    'message_error' => 'Container doesn\'t exist.'));
            }

            // start
            $result = shell_exec("docker start " . $container->getContainerId() . " 2>&1");

            // error cannot start the container
            if (empty($result)) {
                return $this->render('AppBundle:Default:delete.html.twig', array('container' => 'toto',
                    'message_error' => 'Container can\'t start (maybe because an other container is binded to this port).'));
            }

            $container->setIsRunning(1);
            $dm->flush();

            return $this->render('AppBundle:Default:delete.html.twig', array('container' => 'toto',
                'message_accept' => 'Container is started.'));
        }
        else if($action == 'stop') {
            $container = $dm->getRepository(Container::class)->find($image);

            // container doesn't exist
            if(!$container) {
                return $this->render('AppBundle:Default:delete.html.twig', array('container' => 'toto',
                    'message_error' => 'Container doesn\'t exist.'));
            }

            shell_exec("docker stop " . $container->getContainerId() . " 2>&1");
            $container->setIsRunning(0);
            $dm->flush();

            return $this->render('AppBundle:Default:delete.html.twig', array('container' => 'toto',
                'message_accept' => 'Container is stoped.'));
        }

        // if reached there's a problem in the url
        throw $this->createNotFoundException('Error 404');
    }

    /**
     * @Route("/", name="home")
     */
    public function indexAction(Request $request)
    {
        // it works but it's not beautiful, needed to change
        $dm = $this->getDoctrine()->getManager();
        $query = $dm->createQuery("SELECT count('id') FROM AppBundle:DCFile");
        $file = $query->execute();
        $query = $dm->createQuery("SELECT COUNT(f) FROM AppBundle:DCFile f WHERE f.type = 1");
        $file_type1 = $query->execute();
        $query = $dm->createQuery("SELECT COUNT(f) FROM AppBundle:DCFile f WHERE f.type = 2");
        $file_type2 = $query->execute();

        $query = $dm->createQuery("SELECT count('id') FROM AppBundle:Image");
        $image_all = $query->execute();
        $query = $dm->createQuery("SELECT count(i) FROM AppBundle:Image i WHERE i.isfromdf = 0");
        $image_pull = $query->execute();
        $query = $dm->createQuery("SELECT count(i) FROM AppBundle:Image i WHERE i.isfromdf = 1");
        $image_file = $query->execute();

        $query = $dm->createQuery("SELECT count(co) FROM AppBundle:Container co");
        $container_all = $query->execute();
        $query = $dm->createQuery("SELECT count(co) FROM AppBundle:Container co WHERE co.isrunning = 1");
        $container_run = $query->execute();
        $query = $dm->createQuery("SELECT count(co) FROM AppBundle:Container co WHERE co.isrunning = 0");
        $container_norun = $query->execute();

        return $this->render('AppBundle:Default:home.html.twig', array(
            'file' => $file[0][1],
            'file_type1'=> $file_type1[0][1],
            'file_type2'=> $file_type2[0][1],
            'image_all' => $image_all[0][1],
            'image_pull' => $image_pull[0][1],
            'image_file' => $image_file[0][1],
            'container_all' => $container_all[0][1],
            'container_run' => $container_run[0][1],
            'container_norun' => $container_norun[0][1]
        ));
    }
}
