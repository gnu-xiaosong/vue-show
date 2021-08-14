<template>
  <div class="word-wrap">
    <div class="doc_title">
      <h4>{{ title }}</h4>
    </div>
    <!--docx显示-->
    <div class="docx" v-show="control == 'docx'">
      <div class="right"></div>
      <div class="left"></div>
      <div id="docx" class="docx_son" v-html="outputHtml" />
    </div>

    <!--excel显示-->
    <div class="excel" v-html="outputHtml" v-show="control == 'excel'"></div>
    <!--pdf显示-->
    <div class="pdf" v-show="control == 'pdf'">
      <div>
        <button type="button" @click="changePdfPage(0)">上一页</button>
        <button type="button" @click="changePdfPage(1)">下一页</button>
        <button type="button" @click="scaleD()">放大</button>
        <button type="button" @click="scaleX()">缩小</button>
        <button type="button" @click="clock()">顺时针</button>
        <button type="button" @click="counterClock()">逆时针</button>
        <p>{{ pdfOptions.currentPage }} / {{ pdfOptions.pageCount }}</p>
      </div>

      <div
        style="width:600px;heigth:600px;padding-left:35px;padding-bottom:20px"
      >
        <pdf
          ref="pdf"
          :src="pdfOptions.src"
          :page="pdfOptions.currentPage"
          :rotate="pdfOptions.pageRotate"
          @num-pages="pdfOptions.pageCount = $event"
          @page-loaded="pdfOptions.currentPage = $event"
          @loaded="loadPdfHandler"
          style="width:600px;heigth:600px;"
        ></pdf>
      </div>
    </div>

    <!--调用微软接口显示-->
    <div class="mcriApi" v-show="control == 'inline'">
      <iframe :src="fileURL" frameborder="0"></iframe>
    </div>
  </div>
</template>

<script>
// docx文档预览(只能转换.docx文档，转换过程中复杂样式被忽，居中、首行缩进等)

/**使用说明******
 * 安装依赖： npm i vue-pdf mammoth xlsx
 *prop参数：
 data = {
      //模式选择  inlineApi----调用微软的接口  auto 自动 默认自动(本地调用优先级最高)
      mode:,
      // 文件网络url
      url: this.data.url,
      // excel是否可编辑---默认不可编辑
      excelEditAble:
    };
*/

import pdf from "vue-pdf";
import mammoth from "mammoth";
import XLSX from "xlsx";
export default {
  props: {
    data: Object,
  },
  components: {
    pdf,
  },
  data() {
    return {
      // 控制显示
      control: "",
      // 显示的标题
      title: "文件在线阅览",
      // 解析后的html数据
      outputHtml: "",
      //  文件路径(后台文件路径)
      fileURL: this.data.url.length == 0 ? "/source/test.pdf" : this.data.url,
      // docx返回的文本
      text: "",
      // excel显示输出HTMl参数
      excelOptions: {
        id: "excel", //制定输出HTML中table的id
        editable: this.data.excelEditAble, // 是否可编辑 默认false
        header: "", // Override header (default html body)
        footer: "", // Override footer (default /body /html)
      },
      // PDF参数
      pdfOptions: {
        src:
          "http://storage.xuetangx.com/public_assets/xuetangx/PDF/PlayerAPI_v1.0.6.pdf",
        currentPage: 0, // pdf文件页码
        pageCount: 0, // pdf文件总页数
        scale: 100,
        pageRotate: 0,
      },
    };
  },
  created() {
    /**********执行周期*************/
    this.data = {
      //模式选择  inlineApi----调用微软的接口  auto 自动 默认自动(本地调用优先级最高)
      mode: typeof this.data.mode == "undefined" ? "auto" : this.data.mode,
      // 文件网络url
      url: this.data.url,
      // excel是否可编辑---默认不可编辑
      excelEditAble:
        typeof this.data.excelEditAble == "undefined"
          ? false
          : this.data.excelEditAble,
    };

    // 判断执行优先级
    if (this.data.mode == "inlineApi") {
      // 用户自定义在线接口调用
      this.getMcriOfficeApi();
      this.control = "inline";
    } else {
      // 默认执行
      this.auto();
    }
  },
  methods: {
    // 默认执行
    auto() {
      //  判断文件类型
      var fileType = this.fileURL.split(".")[
        this.fileURL.split(".").length - 1
      ];
      if (fileType == "xlsx") {
        // excel 文件
        this.getExcelText(this.fileURL);
        this.control = "xlsx";
      } else if (fileType == "docx") {
        // docx
        // 生成html
        this.getWordText("html", this.fileURL);
        // 生成文本
        this.getWordText("text", this.fileURL);
        this.control = "docx";
      } else if (fileType == "pdf") {
        // pdf
        this.control = "pdf";
      } else {
        // 最低优先级
        this.getMcriOfficeApi();
        this.control = "inline";
      }
    },
    // 调用微软的接口显示-----优先最级
    getMcriOfficeApi() {
      // 拼接url----需要能公网访问
      this.fileURL = `https://view.officeapps.live.com/op/view.aspx?src=${this.fileURL}`;
    },
    // excel显示
    getExcelText(url) {
      var xhr = new XMLHttpRequest();
      xhr.open("get", url, true);
      xhr.responseType = "arraybuffer";
      let _this = this;

      xhr.onload = function(e) {
        console.log(e);
        var binary = "";
        if (xhr.status === 200) {
          // 获取
          var bytes = new Uint8Array(xhr.response);
          var length = bytes.byteLength;
          for (var i = 0; i < length; i++) {
            binary += String.fromCharCode(bytes[i]);
          }
          var wb = XLSX.read(binary, { type: "binary" });

          // excel标题
          var wsname = wb.SheetNames[0];
          this.excel_title = wsname;
          this.title = wsname;
          console.log(wsname);
          var ws = wb.Sheets[wsname];
          // 生成的html
          var HTML = XLSX.utils.sheet_to_html(ws, _this.excelOptions);
          // html数据处理
          // HTML = HTML.replace(/<span/g, "<p").replace(/<\/span>/g, "</p>");
          console.log("HTML:");
          _this.outputHtml = HTML;
          console.log(HTML);
        }
      };
      xhr.send();
    },
    // 生成word转html函数----仅支持docx格式
    getWordText(type = "html", wordURL) {
      /*
       *参数说明：
       *type string 输出模式 (html:输出html, text:输出文本)  默认html
       *url  string docx文件路径(后台路径)  注意跨域问题
       *return text string 返回解析字符串
       */
      const xhr = new XMLHttpRequest();
      xhr.open("get", wordURL, true);
      xhr.responseType = "arraybuffer";
      xhr.onload = () => {
        if (xhr.status == 200) {
          if (type == "html") {
            // 生成html代码
            mammoth
              .convertToHtml({ arrayBuffer: new Uint8Array(xhr.response) })
              .then((resultObject) => {
                this.outputHtml = resultObject.value;
              });
          } else {
            //  生成text文本
            mammoth
              .extractRawText({ arrayBuffer: new Uint8Array(xhr.response) })
              .then((result) => {
                this.text = result.value; // The raw text
              })
              .done();
          }
        }
      };
      xhr.send();
    },

    // pdf加载时
    loadPdfHandler(e) {
      e;
      this.pdfOptions.currentPage = 1; // 加载的时候先加载第一页
    },
    // 改变PDF页码,val传过来区分上一页下一页的值,0上一页,1下一页
    changePdfPage(val) {
      if (val === 0 && this.pdfOptions.currentPage > 1) {
        this.pdfOptions.currentPage--;
      }
      if (
        val === 1 &&
        this.pdfOptions.currentPage < this.pdfOptions.pageCount
      ) {
        this.pdfOptions.currentPage++;
      }
    },
    //放大
    scaleD() {
      this.pdfOptions.scale += 5;
      this.$refs.pdf.$el.style.width = parseInt(this.pdfOptions.scale) + "%";
    },
    //缩小
    scaleX() {
      if (this.pdfOptions.scale === 100) {
        return;
      }
      this.pdfOptions.scale += -5;
      this.$refs.pdf.$el.style.width = parseInt(this.pdfOptions.scale) + "%";
    },
    // 顺时针
    clock() {
      this.pdfOptions.pageRotate += 90;
    },
    // 逆时针
    counterClock() {
      this.pdfOptions.pageRotate -= 90;
    },
  },
};
</script>

<style sceped>
.word-wrap {
  padding-top: 10px;
  background-color: #e6e6e6;
}
.excel-container {
  width: 100%;
}
table {
  table-layout: fixed !important;
  width: 100% !important;
  border-collapse: collapse;
  border: none;
  font-size: 0.23rem;
}

td,
th {
  width: 1px;
  white-space: nowrap; /* 自适应宽度*/
  word-break: keep-all; /* 避免长单词截断，保持全部 */
  border: solid #d4d4d4 1px;
  text-align: center;
  white-space: pre-line;
  word-break: break-all !important;
  word-wrap: break-word !important;
  display: table-cell;
  vertical-align: middle !important;
  white-space: normal !important;
  height: auto;
  vertical-align: text-top;
  padding: 2px 2px 0 2px;
  display: table-cell;
}

#excel span {
  outline: none;
}

#excel td:hover {
  border: 1px solid #4b89ff;
  background-color: #edf3ff;
}

.word-wrap .pdf {
  display: block;
  width: 670px;
  height: 960px;
  margin: auto;
  text-align: center;
  border-radius: 80px;
  padding: 50px 50px;

  background: url(../assets/边框.svg) no-repeat -252px -93px;
  background-size: 160%;
  background-color: #ffcf6e;
}

.pdf button {
  color: aliceblue;
  border-radius: 15px;
  background-color: #007acc;
  margin-left: 2px;
  box-shadow: 2px 2px 2px 2px;
}

.pdf p {
  font-weight: 700px;
  color: #007acc;
}

.docx {
  position: relative;
  width: 754px;

  margin: auto;
  overflow: hidden;
  background-color: #ffffff;
}

.docx .docx_son {
  width: 530px;
  height: auto;
  margin: 90px auto;
}

.docx .left {
  position: absolute;
  top: calc(90px - 25px);
  left: calc(112px - 25px);
  width: 25px;
  height: 25px;
  border-bottom: 1px solid #aaaaaa;
  border-right: 1px solid #aaaaaa;
}

.docx .right {
  position: absolute;
  top: calc(90px - 25px);
  left: calc(112px - 25px + 530px + 25px);
  width: 25px;
  height: 25px;
  border-bottom: 1px solid #aaaaaa;
  border-left: 1px solid #aaaaaa;
}

.word-wrap .excel {
  margin: 20px 100px;
  background-color: #ffffff;
}

.word-wrap .doc_title {
  text-align: center;
}
</style>
