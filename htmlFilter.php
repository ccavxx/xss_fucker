<?php

include_once 'htmlParser.php';

class htmlFilter
{
    private $_lastError = 0;
    private $_lastErrorMsg = "";
    private $_htmlDom = null;
    private $_safeDom = null;
    
    //：：：选项：：：是否在输入数据中无法找到任意有效HTML标签时尝试自动闭合
    //该选项可以过滤类似于input的value注入或img的src注入或其他dom注入，例如：x" onerror="alert(/xss/)的payload可以被完整过滤
    //但该选项开启可能会造成错误过滤，例如输入：if select == "abcdefg" or select >= 5 then aaa = "ccc"
    //将产生输出：if select == ">= 5 then aaa = "ccc"
    //请自行决定是否默认开启，或使用setAutoClosing方法将其动态开关
    private $_opt_autoclosing = true;

    const ERR_CODE_OK = 0;
    const ERR_CODE_BAD_HTML = 1;
    const ERR_CODE_EMPTY_HTML = 2;

    const ERR_MSG_OK = "";
    const ERR_MSG_SHD_NOT_FOUND = "class 'simple_html_dom' NOT found";
    const ERR_MSG_SCP_NOT_FOUND = "class 'simple_css_parser' NOT found";
    const ERR_MSG_SHDN_NOT_FOUND = "class 'simple_html_dom_node' NOT found";
    const ERR_MSG_SUF_NOT_FOUND = "class 'simple_url_filter' NOT found";
    const ERR_MSG_BAD_HTML = "Bad HTML string";
    const ERR_MSG_EMPTY_HTML = "Empty HTML string";


    /*
     * 设置白名单标签，及允许的属性，除非该标签是白名单，否则剥离该标签（但保留标签内容）
     * 注意，这里根据实际需要同样移除了form标签，以防止用户伪造一个登录或搜集信息的表单。
     * 该参数全为小写
     */
    private $_ALLOW_TAGS = array(
        "a" => array("class", "title", "style", "dir", "lang", "xml:lang", "charset", "coords", "href", "hreflang", "name", "rel", "rev", "shape", "target", "type"),
        "abbr" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "acronym" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "address" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "area" => array("class", "title", "style", "dir", "lang", "xml:lang", "alt", "coords", "href", "nohref", "shape", "target"),
        "b" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "bdo" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "big" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "blockquote" => array("class", "title", "style", "dir", "lang", "xml:lang", "cite"),
        "br" => array("class", "title", "style"),
        "button" => array("class", "title", "style", "dir", "lang", "xml:lang", "tabindex", "disabled", "name", "type", "value", "size"),
        "caption" => array("class", "title", "style", "dir", "lang", "xml:lang", "alignspan"),
        "center" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "cite" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "col" => array("class", "title", "style", "dir", "lang", "xml:lang", "align", "char", "charoff", "span", "valign", "width"),
        "colgroup" => array("class", "title", "style", "dir", "lang", "xml:lang", "align", "char", "charoff", "span", "valign", "width"),
        "dd" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "del" => array("class", "title", "style", "dir", "lang", "xml:lang", "cite", "datetime"),
        "dfn" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "div" => array("class", "title", "style", "dir", "lang", "xml:lang", "data-widget-type", "data-widget-config"),
        "dl" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "dt" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "em" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "embed" => array("class", "title", "style", "dir", "lang", "xml:lang", "allowscriptaccess", "allownetworking", "flashvars", "height", "name", "quality", "src", "type", "var", "width", "wmode", "border", "contenteditable", "pluginspage", "play", "loop", "menu"),
        "fieldset" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "font" => array("class", "title", "style", "dir", "lang", "xml:lang", "color", "face", "size"),
        "h1" => array("class", "title", "style", "dir", "lang", "xml:lang", "align"),
        "h2" => array("class", "title", "style", "dir", "lang", "xml:lang", "align"),
        "h3" => array("class", "title", "style", "dir", "lang", "xml:lang", "align"),
        "h4" => array("class", "title", "style", "dir", "lang", "xml:lang", "align"),
        "h5" => array("class", "title", "style", "dir", "lang", "xml:lang", "align"),
        "h6" => array("class", "title", "style", "dir", "lang", "xml:lang", "align"),
        "hr" => array("class", "title", "style", "dir", "lang", "xml:lang", "align", "noshade", "size", "width"),
        "marquee" => array("class", "title", "style", "dir", "lang", "xml:lang", "behavior", "direction", "scrolldelay", "scrollamount", "loop", "width", "height", "vspace", "hspace", "bgcolor"),
        "i" => array("class", "contenteditable", "contextmenu", "dir", "draggable", "irrelevant", "lang", "ref", "registrationmark", "tabindex", "template", "title"),
        "img" => array("class", "title", "style", "lang", "xml:lang", "alt", "src", "align", "border", "height", "hspace", "ismap", "long", "desc", "usemap", "vspace", "width"),
        "input" => array("class", "title", "style", "lang", "xml:lang", "alt", "checked", "disabled", "maxlength", "name", "readonly", "size", "src", "tabindex", "type", "usemap", "value"),
        "ins" => array("class", "title", "style", "lang", "xml:lang", "cite", "datetime"),
        "kbd" => array("class", "title", "style", "lang", "xml:lang"),
        "label" => array("class", "title", "style", "lang", "xml:lang", "for"),
        "legend" => array("class", "title", "style", "lang", "xml:lang", "align"),
        "li" => array("class", "title", "style", "dir", "lang", "xml:lang", "type", "value"),
        "map" => array("class", "title", "style", "dir", "lang", "xml:lang", "name"),
        "ol" => array("class", "title", "style", "dir", "lang", "xml:lang", "compact", "start", "type"),
        "optgroup" => array("class", "title", "style", "dir", "lang", "xml:lang", "label", "disabled"),
        "option" => array("class", "title", "style", "dir", "lang", "xml:lang", "disabled", "label", "selected", "value"),
        "p" => array("class", "title", "style", "dir", "lang", "xml:lang", "align"),
        "pre" => array("class", "title", "style", "dir", "lang", "xml:lang", "xml:space", "width"),
        "q" => array("class", "title", "style", "dir", "lang", "xml:lang", "cite"),
        "s" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "select" => array("class", "title", "style", "dir", "lang", "xml:lang", "accesskey", "tabindex", "disabled", "multiple", "name", "size"),
        "small" => array("class", "title", "style", "dir", "lang"),
        "span" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "strike" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "strong" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "sub" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "sup" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "table" => array("class", "title", "style", "dir", "lang", "xml:lang", "align", "bgcolor", "border", "cellpadding", "cellspacing", "frame", "rules", "summary", "width"),
        "tbody" => array("class", "title", "style", "dir", "lang", "xml:lang", "align", "char", "charoff", "valign"),
        "td" => array("class", "title", "style", "dir", "lang", "xml:lang", "abbr", "align", "axis", "bgcolor", "char", "charoff", "colspan", "headers", "height", "nowrap", "rowspan", "scope", "valign", "width"),
        "textarea" => array("class", "title", "style", "dir", "lang", "xml:lang", "cols", "rows", "disabled", "name", "readonly"),
        "tfoot" => array("class", "title", "style", "dir", "lang", "xml:lang", "align", "char", "charoff", "valign"),
        "th" => array("class", "title", "style", "dir", "lang", "xml:lang", "abbr", "align", "axis", "bgcolor", "char", "charoff", "colspan", "headers", "height", "nowrap", "rowspan", "scope", "valign", "width"),
        "thead" => array("class", "title", "style", "dir", "lang", "xml:lang", "align", "char", "charoff", "valign"),
        "tr" => array("class", "title", "style", "dir", "lang", "xml:lang", "align", "bgcolor", "char", "charoff", "valign"),
        "tt" => array("class", "title", "style", "dir", "lang"),
        "u" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "ul" => array("class", "title", "style", "dir", "lang", "xml:lang", "compact", "type"),
        "var" => array("class", "title", "style", "dir", "lang", "xml:lang"),
        "section" => array("class", "title", "style", "dir", "lang", "xml:lang"),

        //一些H5特有的标签
        "article" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "aside" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "audio" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable", "autoplay", "controls", "loop", "muted", "preload", "src"),
        "bdi" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "datalist" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "details" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "figcaption" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "figure" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "mark" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "progress" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable", "max", "value"),
        "source" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable", "media", "src", "type"),
        "summary" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),
        "time" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable", "datetime", "pubdate"),
        "track" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable", "src", "srclang", "label", "kind", "default"),
        "video" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable", "autoplay", "controls", "height", "loop", "muted", "poster", "preload", "src", "width"),
        "wbr" => array("class", "title", "style", "dir", "lang", "xml:lang", "spellcheck", "translate", "hidden", "dropzone", "draggable", "contenteditable"),

        //其他一些自定义标签
        "code" => array(),
        "comment" => array(),
        "_" => array(
            "class","title","style","dir","lang","xml:lang","charset","coords","href","hreflang","name","rel","rev","shape","target","type","alt","nohref","cite","tabindex","disabled","value","size","alignspan","align","char","charoff","span","valign","width","datetime","data-widget-type","data-widget-config","allowscriptaccess","allownetworking","flashvars","height","quality","src","var","wmode","border","contenteditable","pluginspage","play","loop","menu","color","face","noshade","behavior","direction","scrolldelay","scrollamount","vspace","hspace","bgcolor","contextmenu","draggable","irrelevant","ref","registrationmark","template","ismap","long","desc","usemap","checked","maxlength","readonly","for","compact","start","label","selected","xml:space","accesskey","multiple","cellpadding","cellspacing","frame","rules","summary","abbr","axis","colspan","headers","nowrap","rowspan","scope","cols","rows","spellcheck","translate","hidden","dropzone","autoplay","controls","muted","preload","max","media","pubdate","srclang","kind","default","poster"
        ),     //这是一个特殊的标签，用于检测当某段输入可能只是某个html标签的一部分时，使用该特殊标签强行闭合，并进行预先过滤
        "root" => array(),  //simple_html_dom内置的一个特殊标签，表示DOM树的根节点，不允许其附带任何属性，同时在输出时将跳过该标签
        "text" => array()   //simple_html_dom内置的一个特殊标签，表示标签中包含的一段文本，不允许其附带任何属性，输出时会经过处理后展示
    );

    /*
     * 设置某些标签必选属性
     * 如果该必选属性强制为特定值，则标明特定值，否则标记null。
     */
    private $_TAG_BASE_ATTRS = array(
        "embed" => array("allowscriptaccess" => "nerver"),
        "img" => array("src" => null),
        "optgroup" => array("label" => null),

        "audio" => array("src" => null),
        "source" => array("src" => null),
        "track" => array("src" => null),
        "video" => array("src" => null)
    );


    function __construct($html=null, $autoclosing=null)
    {
        if (!class_exists("simple_html_dom", true)) exit(self::ERR_MSG_SHD_NOT_FOUND);
        if (!class_exists("simple_html_dom_node", true)) exit(self::ERR_MSG_SHDN_NOT_FOUND);
        if (!class_exists("simple_css_parser", true)) exit(self::ERR_MSG_SCP_NOT_FOUND);
        if (!class_exists("simple_url_filter", true)) exit(self::ERR_MSG_SUF_NOT_FOUND);
        
        if (is_bool($autoclosing)) $this->setAutoClosing($autoclosing);
        if (is_string($html)) $this->safeHTML($html);
    }
    
    public function setAutoClosing($switch=false)
    {
        $this->_opt_autoclosing = ($switch === true) ? true : false;
        return $this;
    }

    //尝试解析HTML字符串，并转换为DOM对象
    private function _parserHTML($html_string="")
    {
        if (!is_string($html_string))
        {
            $this->_setError(self::ERR_CODE_BAD_HTML, self::ERR_MSG_BAD_HTML);
            return false;
        }

        $this->_htmlDom = new simple_html_dom($html_string);
        $this->_setError();
        return true;
    }

    private function _setError($errorCode=self::ERR_CODE_OK, $errorMsg=self::ERR_MSG_OK)
    {
        $this->_lastError = $errorCode;
        $this->_lastErrorMsg = $errorMsg;
    }

    function getLastError($intext=false)
    {
        if (!$intext) return $this->_lastError;
        return $this->_lastErrorMsg;
    }

    function safeHTML($html_string="", $autoclose=false)
    {

        $this->_safeDom = null;
        $this->_parserHTML($html_string);

        //如果当前输入包含0个有效的HTML标签，则启动强行闭合
        if (!isset($this->_htmlDom->root->children) || empty($this->_htmlDom->root->children))
        {
            //如果强行闭合后还是没有符合条件的HTML标签，则返回源字符串（应该来说不会发生这个情况吧呵呵哒）
            if ($autoclose === true)
            {
                $this->_setError(self::ERR_CODE_EMPTY_HTML, self::ERR_MSG_EMPTY_HTML);
                return substr($html_string, 8, -6);  //掐头去尾
            }
            
            if ($this->_opt_autoclosing !== true) return $html_string;
            
            $html_string_1 = "<_ dir=\"" . $html_string . ' \'" />';   //添加特殊标签
            $html_string_2 = "<_ dir='" . $html_string . ' \'" />';   //添加特殊标签
            
            $result1 = $this->safeHTML($html_string_1, true);
            $result2 = $this->safeHTML($html_string_2, true);
            
            $outputHTML = (strlen($result1) > strlen($result2)) ? $result2 : $result1; 
            
            return $outputHTML;
        }

        $this->_safeHTML($this->_htmlDom);

        $outputHTML = $this->_htmlDom->__toString();
        if ($autoclose === true && $this->_opt_autoclosing === true)
        {
            if ($outputHTML === "<_ />" || $outputHTML === "<_>" || $outputHTML === "<_/>" || $outputHTML === "<_ >")   //如果添加特殊标签后被完整过滤，则认为提交的内容不包含任何HTML倾向，完全放行
            {
                echo $outputHTML."<br />";
                $outputHTML = $html_string;
            }
            
            return substr($outputHTML, 8, -6);
        }
        return $outputHTML;

    }


    private function _safeHTML(&$node)
    {
        if (isset($node->children) && !empty($node->children))
        {
            foreach ($node->children as $_key=>$_children_node)
            {
                //var_dump($_children_node);
                if ($_children_node instanceof simple_html_dom_node)
                {
                    $this->_safeHTML($node->children[$_key]);
                }
            }
        }

        if (isset($node->nodes) && !empty($node->nodes))
        {
            foreach ($node->nodes as $_key=>$_sub_node)
            {
                if ($_sub_node instanceof simple_html_dom_node)
                {

                    //检查标签是否允许
                    if (!isset($this->_ALLOW_TAGS[$_sub_node->tag]))
                    {
                        //echo "标签 {$_sub_node->tag}, 不被允许,已删除.\n";

                        //清空这个标签及下面的所有内容
                        $node->nodes[$_key]->outertext = "";  //不标准的用法，直接操作了对象属性
                        $node->nodes[$_key]->innertext = "";
                        continue;
                    }

                    //检查属性是否允许
                    foreach ($_sub_node->attr as $_attr_name => $_attr_value)
                    {
                        if (!in_array($_attr_name, $this->_ALLOW_TAGS[$_sub_node->tag]))
                        {
                            $node->nodes[$_key]->removeAttribute($_attr_name);
                        }else{
                            //TODO 对CSS属性（style）进行解析和预过滤

                            //检查属性合法性
                            if (isset($this->_TAG_BASE_ATTRS[$_sub_node->tag][$_attr_name]))
                            {
                                //如果该属性要被强制覆盖的，则强制覆盖它
                                if (!is_null($this->_TAG_BASE_ATTRS[$_sub_node->tag][$_attr_name]))
                                {
                                    $node->nodes[$_key]->setAttribute($_attr_name, (string)$this->_TAG_BASE_ATTRS[$_sub_node->tag][$_attr_name]);
                                }
                            }
                        }
                    }

                    if (isset($this->_TAG_BASE_ATTRS[$_sub_node->tag]))
                    {
                        //然后检查是否缺失必选属性，缺失必选属性的，直接砍死
                        foreach ($this->_TAG_BASE_ATTRS[$_sub_node->tag] as $_base_attr_name => $_base_attr_value)
                        {
                            $_node_attr_value = $_sub_node->getAttribute($_base_attr_name);
                            if (empty($_node_attr_value))
                            {
                                //echo "标签 {$_sub_node->tag}, 缺失必要属性 {$_base_attr_name},已删除.\n";

                                $node->nodes[$_key]->outertext = "";  //不标准的用法，直接操作了对象属性
                                $node->nodes[$_key]->innertext = "";
                                break;
                            }
                        }
                    }

                }
            }
        }

        return true;
    }

}

