<?php

namespace Contao;

/**
 * Contao Open Source CMS - tags extension
 *
 * Copyright (c) 2008-2016 Helmut Schottmüller
 *
 * @license LGPL-3.0+
 */

class ModuleGlobalArticlelist extends \Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_global_articlelist';


	/**
	 * Do not display the module if there are no articles
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### GLOBAL ARTICLE LIST ###';

			return $objTemplate->parse();
		}

		$this->strTemplate = (strlen($this->articlelist_template)) ? $this->articlelist_template : $this->strTemplate;
		return parent::generate();
	}


	/**
	 * Generate module
	 */
	protected function compile()
	{
		global $objPage;

		// block this method to prevent recursive call of getArticle if the HTML of an article is the same as the current article
		if ($this->Session->get('block'))
		{
			$this->Session->set('block', false);
			return;
		}
		$this->Session->set('block', true);
		$articles = array();
		$id = $objPage->id;

		$this->Template->request = \Environment::get('request');

		$time = time();

		// Get published articles
		$objArticles = $this->Database->prepare("SELECT id, title, inColumn, cssID FROM tl_article" . (!BE_USER_LOGGED_IN ? " WHERE (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" : "") . " ORDER BY title")
			->execute($time, $time);

		$tagids = array();
		if (strlen(\Input::get('tag')))
		{
            $this->Template->fullList = false;
		    $currentTag =  urldecode(\Input::get('tag'));

			$limit = null;
			$offset = 0;
			
			$objIds = $this->Database->prepare("SELECT tid FROM tl_tag WHERE from_table = ? AND tag = ?")
				->execute('tl_article', $currentTag);
			if ($objIds->numRows)
			{
				while ($objIds->next())
				{
					array_push($tagids, $objIds->tid);
				}
			}
		}else{
            $this->Template->fullList = true;
			$limit = null;
			$offset = 0;

			$objIds = $this->Database->prepare("SELECT tid FROM tl_tag WHERE from_table = ? group by tid")
				->execute('tl_article');
			if ($objIds->numRows)
			{
				while ($objIds->next())
				{
					array_push($tagids, $objIds->tid);
				}
			}

        }

		while ($objArticles->next())
		{
			$cssID = StringUtil::deserialize($objArticles->cssID, true);

			$objArticle = $this->Database->prepare("SELECT a.teaser AS teaser, a.addImage AS addImage, a.singleSRC AS imgSrc, a.id AS aId, a.alias AS aAlias, a.title AS title, p.id AS id, p.alias AS alias, a.teaser FROM tl_article a, tl_page p WHERE a.pid=p.id AND (a.id=? OR a.alias=?)")
										 ->limit(1)
										 ->execute($objArticles->id, $objArticles->id);

			if ($objArticle->numRows)
			{
				if (count($tagids) || !$this->hide_on_empty)
				{
					if (in_array($objArticle->aId, $tagids) || (!$this->hide_on_empty && count($tagids) == 0))
					{

                        $teaser = !empty($objArticle->teaser) ? $objArticle->teaser : '';

                        if ($this->linktoarticles) { // link to articles
                            $articles[] = array(
                                'addImage' => !empty($objArticle->addImage),
                                'image' =>  '<img src="'.\FilesModel::findByUuid($objArticle->imgSrc)->path .'" width="325" height="244">',
                                'content' => '{{article::' . $objArticle->aId . '}}',
                                'url' => '{{article_url::'. $objArticle->aId . '}}',
                                'tags' => '{{tags_article::'. $objArticle->aId. '}}',
                                'pageUrl' =>'{{link_url::'. $objArticle->id. '}}',
                                'data' => $objArticle->row(),
                                'html' => $this->getArticle($objArticle->aId, false, true),
                                'teaser' => $teaser
                            );
						} else { // link to pages
                            $articles[] = array(
                                'addImage' => !empty($objArticle->addImage),
                                'image' =>  '<img src="'.\FilesModel::findByUuid($objArticle->imgSrc)->path .'" width="325" height="244">',
                                'content' => '{{link::' . $objArticle->id . '}}',
                                'url'     => '{{link_url::' . $objArticle->id . '}}',
                                'tags'    => '{{tags_article::' . $objArticle->aId . '}}',
                                'data'    => $objArticle->row(),
                                'html'    => $this->getArticle($objArticle->aId, false, true),
                                'teaser'  => $teaser
                            );
						}
					}
				}
			}
		}
		


		$headlinetags = array();
		if (strlen(\Input::get('tag')))
		{
			$relatedlist = (strlen(\Input::get('related'))) ? preg_split("/,/", \Input::get('related')) : array();
			$headlinetags = array_merge(array($currentTag), $relatedlist);
		}
		$this->Template->tags_activetags = $headlinetags;
		$this->Template->articles = $articles;
		$this->Template->empty = $GLOBALS['TL_LANG']['MSC']['emptyarticles'];
		$this->Session->set('block', false);
	}
}

